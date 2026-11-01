<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class LicenseController
{
    public function check(Request $request): void
    {
        $licenseKey = trim((string)$request->get('license_key', ''));
        $deviceId = trim((string)$request->get('device_id', ''));
        $clientVersion = trim((string)$request->get('client_version', ''));

        if (empty($licenseKey)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'license_key is required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM licenses WHERE license_key = :k LIMIT 1');
        $stmt->execute([':k' => $licenseKey]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$license) {
            Response::error(Response::ERR_LICENSE_INVALID, 'License key does not exist.', 404);
        }

        if ($license['status'] === 'disabled') {
            Response::error(Response::ERR_LICENSE_INVALID, 'License has been disabled.', 403);
        }

        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            Response::error(Response::ERR_LICENSE_EXPIRED, 'License has expired.', 403);
        }

        // Count registered devices for this license
        $devCountStmt = $db->prepare('SELECT COUNT(*) FROM devices WHERE license_id = :lid');
        $devCountStmt->execute([':lid' => $license['id']]);
        $deviceCount = (int)$devCountStmt->fetchColumn();

        Response::success([
            'valid'         => true,
            'plan'          => $license['plan'],
            'status'        => $license['status'],
            'expires_at'    => $license['expires_at'],
            'max_devices'   => (int)$license['max_devices'],
            'active_devices'=> $deviceCount
        ]);
    }

    public function activate(Request $request): void
    {
        $licenseKey = trim((string)$request->get('license_key', ''));
        $deviceId = trim((string)$request->get('device_id', ''));

        if (empty($licenseKey) || empty($deviceId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'license_key and device_id are required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM licenses WHERE license_key = :k LIMIT 1');
        $stmt->execute([':k' => $licenseKey]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$license) {
            Response::error(Response::ERR_LICENSE_INVALID, 'License key not found.', 404);
        }

        if ($license['status'] === 'disabled') {
            Response::error(Response::ERR_LICENSE_INVALID, 'License is disabled.', 403);
        }

        // Update license status to active if pending
        if ($license['status'] === 'pending') {
            $upd = $db->prepare('UPDATE licenses SET status = "active", activated_at = NOW() WHERE id = :id');
            $upd->execute([':id' => $license['id']]);
        }

        // Attach device to license
        $updDev = $db->prepare('UPDATE devices SET license_id = :lid WHERE device_uuid = :uuid');
        $updDev->execute([':lid' => $license['id'], ':uuid' => $deviceId]);

        Response::success([
            'activated'   => true,
            'license_key' => $licenseKey,
            'plan'        => $license['plan'],
            'expires_at'  => $license['expires_at']
        ]);
    }

    public function deactivate(Request $request): void
    {
        $licenseKey = trim((string)$request->get('license_key', ''));
        $deviceId = trim((string)$request->get('device_id', ''));

        if (empty($licenseKey) || empty($deviceId)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'license_key and device_id are required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM licenses WHERE license_key = :k LIMIT 1');
        $stmt->execute([':k' => $licenseKey]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($license) {
            $updDev = $db->prepare('UPDATE devices SET license_id = NULL WHERE device_uuid = :uuid AND license_id = :lid');
            $updDev->execute([':uuid' => $deviceId, ':lid' => $license['id']]);
        }

        Response::success([
            'deactivated' => true,
            'device_id'   => $deviceId
        ]);
    }

    public function info(Request $request): void
    {
        $licenseKey = trim((string)$request->get('license_key', ''));
        if (empty($licenseKey)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'license_key parameter required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, license_key, plan, status, max_devices, activated_at, expires_at, created_at FROM licenses WHERE license_key = :k LIMIT 1');
        $stmt->execute([':k' => $licenseKey]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$license) {
            Response::error(Response::ERR_LICENSE_INVALID, 'License not found.', 404);
        }

        Response::success($license);
    }
}
