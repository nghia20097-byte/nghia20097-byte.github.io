<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class BackupController
{
    public function create(Request $request): void
    {
        $deviceId = trim((string)$request->get('device_id', ''));
        $modelId = (int)$request->get('model_id', 0);
        $romId = (int)$request->get('rom_id', 0);
        $type = trim((string)$request->get('type', 'user_data'));
        $sizeBytes = (int)$request->get('size', 0);
        $sha256 = trim((string)$request->get('sha256', ''));
        $storagePath = trim((string)$request->get('storage_path', ''));

        if (empty($deviceId) || $modelId <= 0 || $romId <= 0) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device_id, model_id, and rom_id are required.', 422);
        }

        $db = Database::getConnection();

        // Resolve device internal ID
        $devStmt = $db->prepare('SELECT id FROM devices WHERE device_uuid = :uuid OR id = :uuid LIMIT 1');
        $devStmt->execute([':uuid' => $deviceId]);
        $devDbId = $devStmt->fetchColumn();

        if (!$devDbId) {
            Response::error(Response::ERR_DEVICE_NOT_FOUND, 'Device not registered.', 404);
        }

        $backupCode = 'bkp_' . bin2hex(random_bytes(10));

        $stmt = $db->prepare('
            INSERT INTO backups (backup_code, device_id, model_id, rom_id, backup_type, size_bytes, sha256, storage_path, status)
            VALUES (:code, :did, :mid, :rid, :type, :size, :sha, :path, "pending")
        ');
        $stmt->execute([
            ':code' => $backupCode,
            ':did'  => $devDbId,
            ':mid'  => $modelId,
            ':rid'  => $romId,
            ':type' => $type,
            ':size' => $sizeBytes,
            ':sha'  => !empty($sha256) ? $sha256 : null,
            ':path' => !empty($storagePath) ? $storagePath : null
        ]);

        Response::success([
            'backup_id'  => $backupCode,
            'device_id'  => $deviceId,
            'model_id'   => $modelId,
            'rom_id'     => $romId,
            'type'       => $type,
            'size'       => $sizeBytes,
            'sha256'     => $sha256,
            'status'     => 'pending',
            'created_at' => date('c')
        ], 201);
    }

    public function list(Request $request): void
    {
        $deviceId = $request->getQuery('device_id');
        $db = Database::getConnection();

        $sql = '
            SELECT b.id, b.backup_code, b.backup_type, b.size_bytes, b.sha256, b.status, b.created_at,
                   d.device_uuid, m.model_code AS hardware_model, m.codename, r.name AS rom_name, r.version AS rom_version
            FROM backups b
            JOIN devices d ON d.id = b.device_id
            JOIN models m ON m.id = b.model_id
            JOIN rom_profiles r ON r.id = b.rom_id
        ';
        $params = [];

        if (!empty($deviceId)) {
            $sql .= ' WHERE d.device_uuid = :dev OR d.id = :dev';
            $params[':dev'] = $deviceId;
        }

        $sql .= ' ORDER BY b.id DESC LIMIT 100';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($backups);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT b.*, d.device_uuid, m.model_code AS hardware_model, m.codename, r.name AS rom_name
            FROM backups b
            JOIN devices d ON d.id = b.device_id
            JOIN models m ON m.id = b.model_id
            JOIN rom_profiles r ON r.id = b.rom_id
            WHERE b.id = :id OR b.backup_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            Response::error(Response::ERR_BACKUP_NOT_FOUND, "Backup '{$id}' not found.", 404);
        }

        Response::success($backup);
    }

    public function verify(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $clientSha256 = trim((string)$request->get('sha256', ''));

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, sha256, status FROM backups WHERE id = :id OR backup_code = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            Response::error(Response::ERR_BACKUP_NOT_FOUND, "Backup '{$id}' not found.", 404);
        }

        $isValid = true;
        if (!empty($backup['sha256']) && !empty($clientSha256)) {
            $isValid = hash_equals(strtolower($backup['sha256']), strtolower($clientSha256));
        }

        $newStatus = $isValid ? 'verified' : 'corrupt';
        $upd = $db->prepare('UPDATE backups SET status = :st WHERE id = :id');
        $upd->execute([':st' => $newStatus, ':id' => $backup['id']]);

        Response::success([
            'backup_id' => $id,
            'verified'  => $isValid,
            'status'    => $newStatus
        ]);
    }

    public function delete(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('UPDATE backups SET status = "deleted" WHERE id = :id OR backup_code = :id');
        $stmt->execute([':id' => $id]);

        Response::success([
            'deleted'   => true,
            'backup_id' => $id
        ]);
    }
}
