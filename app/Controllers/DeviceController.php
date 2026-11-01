<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class DeviceController
{
    public function register(Request $request): void
    {
        $clientId = trim((string)$request->get('client_id', ''));
        $clientVersion = trim((string)$request->get('client_version', '1.0.0'));
        $platform = trim((string)$request->get('platform', 'windows'));
        $licenseKey = trim((string)$request->get('license_key', ''));

        if (empty($clientId)) {
            // Generate a new NChanger Device UUID if client didn't supply one
            $clientId = 'ncd_' . bin2hex(random_bytes(16));
        }

        $db = Database::getConnection();

        // Check license if provided
        $licenseId = null;
        if (!empty($licenseKey)) {
            $lStmt = $db->prepare('SELECT id FROM licenses WHERE license_key = :k AND status = "active" LIMIT 1');
            $lStmt->execute([':k' => $licenseKey]);
            $licenseId = $lStmt->fetchColumn() ?: null;
        }

        // Upsert device record
        $stmt = $db->prepare('
            INSERT INTO devices (device_uuid, license_id, client_version, platform, status, last_ip, last_seen_at)
            VALUES (:uuid, :lid, :ver, :plat, "online", :ip, NOW())
            ON DUPLICATE KEY UPDATE
                license_id = COALESCE(:lid, license_id),
                client_version = :ver,
                platform = :plat,
                status = "online",
                last_ip = :ip,
                last_seen_at = NOW()
        ');
        $stmt->execute([
            ':uuid' => $clientId,
            ':lid'  => $licenseId,
            ':ver'  => $clientVersion,
            ':plat' => $platform,
            ':ip'   => $request->getClientIp()
        ]);

        Response::success([
            'device_id'      => $clientId,
            'status'         => 'registered',
            'client_version' => $clientVersion,
            'licensed'       => !empty($licenseId)
        ]);
    }

    public function heartbeat(Request $request): void
    {
        $deviceId = trim((string)$request->get('device_id', ''));
        $codename = trim((string)$request->get('codename', ''));
        $romId = trim((string)$request->get('rom_id', ''));
        $clientVer = trim((string)$request->get('client_version', ''));

        if (empty($deviceId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device_id is required for heartbeat.', 422);
        }

        $db = Database::getConnection();

        // Resolve model ID & ROM ID if supplied
        $modelId = null;
        if (!empty($codename)) {
            $mStmt = $db->prepare('SELECT id FROM models WHERE codename = :c LIMIT 1');
            $mStmt->execute([':c' => $codename]);
            $modelId = $mStmt->fetchColumn() ?: null;
        }

        $romDbId = null;
        if (!empty($romId)) {
            $rStmt = $db->prepare('SELECT id FROM rom_profiles WHERE profile_code = :rc LIMIT 1');
            $rStmt->execute([':rc' => $romId]);
            $romDbId = $rStmt->fetchColumn() ?: null;
        }

        $stmt = $db->prepare('
            UPDATE devices 
            SET status = "online",
                last_seen_at = NOW(),
                last_ip = :ip,
                model_id = COALESCE(:mid, model_id),
                rom_id = COALESCE(:rid, rom_id),
                client_version = COALESCE(:ver, client_version)
            WHERE device_uuid = :uuid
        ');
        $stmt->execute([
            ':uuid' => $deviceId,
            ':ip'   => $request->getClientIp(),
            ':mid'  => $modelId,
            ':rid'  => $romDbId,
            ':ver'  => !empty($clientVer) ? $clientVer : null
        ]);

        Response::success([
            'acknowledged' => true,
            'device_id'    => $deviceId,
            'timestamp'    => date('c')
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT d.*, b.name AS brand, m.model_code AS hardware_model, m.codename, r.name AS rom_name, r.version AS rom_version, l.license_key, l.plan AS license_plan
            FROM devices d
            LEFT JOIN models m ON m.id = d.model_id
            LEFT JOIN brands b ON b.id = m.brand_id
            LEFT JOIN rom_profiles r ON r.id = d.rom_id
            LEFT JOIN licenses l ON l.id = d.license_id
            WHERE d.device_uuid = :id OR d.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            Response::error(Response::ERR_DEVICE_NOT_FOUND, "Device '{$id}' not found.", 404);
        }

        Response::success($device);
    }

    public function status(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT status, last_seen_at FROM devices WHERE device_uuid = :id OR id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_DEVICE_NOT_FOUND, "Device '{$id}' not found.", 404);
        }

        Response::success([
            'device_id'    => $id,
            'status'       => $row['status'],
            'last_seen_at' => $row['last_seen_at']
        ]);
    }

    public function unregister(Request $request): void
    {
        $deviceId = trim((string)$request->get('device_id', ''));
        if (empty($deviceId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device_id is required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE devices SET status = "offline" WHERE device_uuid = :uuid');
        $stmt->execute([':uuid' => $deviceId]);

        Response::success([
            'unregistered' => true,
            'device_id'    => $deviceId
        ]);
    }
}
