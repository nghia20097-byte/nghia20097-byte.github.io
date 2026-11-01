<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class RestoreController
{
    public function prepare(Request $request): void
    {
        $deviceId = trim((string)$request->get('device_id', ''));
        $backupId = trim((string)$request->get('backup_id', ''));

        if (empty($deviceId) || empty($backupId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device_id and backup_id are required.', 422);
        }

        $db = Database::getConnection();

        // 1. Resolve Device
        $dStmt = $db->prepare('SELECT id, model_id, rom_id, status FROM devices WHERE device_uuid = :u OR id = :u LIMIT 1');
        $dStmt->execute([':u' => $deviceId]);
        $device = $dStmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            Response::error(Response::ERR_DEVICE_NOT_FOUND, 'Device not registered.', 404);
        }

        // 2. Resolve Backup
        $bStmt = $db->prepare('SELECT * FROM backups WHERE id = :id OR backup_code = :id LIMIT 1');
        $bStmt->execute([':id' => $backupId]);
        $backup = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            Response::error(Response::ERR_BACKUP_NOT_FOUND, 'Backup record not found.', 404);
        }

        if ($backup['status'] === 'corrupt' || $backup['status'] === 'deleted') {
            Response::error(Response::ERR_RESTORE_NOT_ALLOWED, 'Backup is marked as corrupt or deleted.', 400);
        }

        // 3. Check ROM & Capability
        $rStmt = $db->prepare('SELECT cp.capabilities_json, r.status FROM rom_profiles r LEFT JOIN capability_profiles cp ON cp.rom_id = r.id WHERE r.id = :rid LIMIT 1');
        $rStmt->execute([':rid' => $backup['rom_id']]);
        $rom = $rStmt->fetch(PDO::FETCH_ASSOC);

        $capabilities = json_decode($rom['capabilities_json'] ?? '{}', true);
        if (empty($capabilities['restore']) && ($rom['status'] ?? '') !== 'active') {
            Response::error(Response::ERR_RESTORE_NOT_ALLOWED, 'Restore operation is disabled for this unvalidated ROM profile.', 403);
        }

        Response::success([
            'prepared'         => true,
            'device_id'        => $deviceId,
            'backup_id'        => $backup['backup_code'],
            'model_compatible' => ($device['model_id'] == $backup['model_id']),
            'requires_wipe'    => true,
            'instructions'     => 'Proceed to verify backup checksum before execution.'
        ]);
    }

    public function verify(Request $request): void
    {
        $backupId = trim((string)$request->get('backup_id', ''));
        $sha256 = trim((string)$request->get('sha256', ''));

        if (empty($backupId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'backup_id is required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, backup_code, sha256, status FROM backups WHERE id = :id OR backup_code = :id LIMIT 1');
        $stmt->execute([':id' => $backupId]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            Response::error(Response::ERR_BACKUP_NOT_FOUND, 'Backup record not found.', 404);
        }

        $valid = true;
        if (!empty($backup['sha256']) && !empty($sha256)) {
            $valid = hash_equals(strtolower($backup['sha256']), strtolower($sha256));
        }

        Response::success([
            'backup_id' => $backup['backup_code'],
            'verified'  => $valid,
            'can_apply' => $valid && ($backup['status'] !== 'deleted')
        ]);
    }

    public function execute(Request $request): void
    {
        $deviceId = trim((string)$request->get('device_id', ''));
        $backupId = trim((string)$request->get('backup_id', ''));

        if (empty($deviceId) || empty($backupId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device_id and backup_id are required.', 422);
        }

        $db = Database::getConnection();

        $dStmt = $db->prepare('SELECT id FROM devices WHERE device_uuid = :u OR id = :u LIMIT 1');
        $dStmt->execute([':u' => $deviceId]);
        $devDbId = $dStmt->fetchColumn();

        $bStmt = $db->prepare('SELECT id, backup_code FROM backups WHERE id = :id OR backup_code = :id LIMIT 1');
        $bStmt->execute([':id' => $backupId]);
        $backup = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$devDbId || !$backup) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Invalid device_id or backup_id.', 400);
        }

        $jobCode = 'rst_' . bin2hex(random_bytes(10));

        $stmt = $db->prepare('
            INSERT INTO restore_jobs (job_code, device_id, backup_id, status)
            VALUES (:code, :did, :bid, "in_progress")
        ');
        $stmt->execute([
            ':code' => $jobCode,
            ':did'  => $devDbId,
            ':bid'  => $backup['id']
        ]);

        Response::success([
            'job_id'     => $jobCode,
            'status'     => 'in_progress',
            'device_id'  => $deviceId,
            'backup_id'  => $backup['backup_code'],
            'started_at' => date('c')
        ]);
    }

    public function status(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT rj.job_code, rj.status, rj.error_message, rj.created_at, rj.completed_at,
                   d.device_uuid, b.backup_code
            FROM restore_jobs rj
            JOIN devices d ON d.id = rj.device_id
            JOIN backups b ON b.id = rj.backup_id
            WHERE rj.id = :id OR rj.job_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            Response::error('JOB_NOT_FOUND', "Restore job '{$id}' not found.", 404);
        }

        Response::success($job);
    }

    public function complete(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $status = trim((string)$request->get('status', 'completed'));
        $error = trim((string)$request->get('error', ''));

        $db = Database::getConnection();
        $stmt = $db->prepare('
            UPDATE restore_jobs 
            SET status = :st, error_message = :err, completed_at = NOW() 
            WHERE id = :id OR job_code = :id
        ');
        $stmt->execute([
            ':st'  => in_array($status, ['completed', 'failed']) ? $status : 'completed',
            ':err' => !empty($error) ? $error : null,
            ':id'  => $id
        ]);

        Response::success([
            'job_id'       => $id,
            'status'       => $status,
            'completed_at' => date('c')
        ]);
    }
}
