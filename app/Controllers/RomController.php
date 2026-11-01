<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class RomController
{
    public function index(Request $request): void
    {
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT r.*, b.name AS brand, m.model_code, m.marketing_name, m.codename 
            FROM rom_profiles r
            JOIN models m ON m.id = r.model_id
            JOIN brands b ON b.id = m.brand_id
            WHERE r.status != "disabled"
            ORDER BY r.id DESC
        ');
        $roms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($roms);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT r.*, b.name AS brand, m.model_code, m.marketing_name, m.codename 
            FROM rom_profiles r
            JOIN models m ON m.id = r.model_id
            JOIN brands b ON b.id = m.brand_id
            WHERE r.id = :id OR r.profile_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $rom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rom) {
            Response::error(Response::ERR_ROM_NOT_FOUND, "ROM profile '{$id}' not found.", 404);
        }

        if (!empty($rom['manifest_json'])) {
            $rom['manifest'] = json_decode($rom['manifest_json'], true);
        }

        Response::success($rom);
    }

    public function manifest(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT manifest_json, profile_code FROM rom_profiles WHERE id = :id OR profile_code = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_ROM_NOT_FOUND, "ROM profile '{$id}' not found.", 404);
        }

        Response::success(json_decode($row['manifest_json'] ?? '{}', true));
    }

    public function check(Request $request): void
    {
        $device = $request->get('device', []);
        $rom = $request->get('rom', []);

        $codename = trim($device['codename'] ?? '');
        $apiLevel = (int)($rom['api_level'] ?? 0);
        $buildId = trim($rom['build_id'] ?? '');

        if (empty($codename)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'device.codename is required for ROM verification.', 422);
        }

        $db = Database::getConnection();

        // 1. Find matching model & ROM
        $stmt = $db->prepare('
            SELECT r.*, cp.capabilities_json
            FROM rom_profiles r
            JOIN models m ON m.id = r.model_id
            LEFT JOIN capability_profiles cp ON cp.rom_id = r.id
            WHERE m.codename = :codename
              AND (r.api_level = :api OR :api = 0)
            ORDER BY (r.build_id = :buildId) DESC, r.id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':codename' => $codename,
            ':api'      => $apiLevel,
            ':buildId'  => $buildId
        ]);
        $foundRom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$foundRom) {
            // Unrecognized ROM -> Safe Mode
            Response::success([
                'supported'   => false,
                'reason'      => 'rom_not_supported',
                'safe_mode'   => true,
                'capabilities'=> [
                    'device_info' => true,
                    'diagnostics' => true,
                    'backup'      => false,
                    'restore'     => false
                ]
            ]);
            return;
        }

        $capabilities = json_decode($foundRom['capabilities_json'] ?? '{}', true);

        // 2. If status is pending_validation -> Safe Mode fallback
        if ($foundRom['status'] === 'pending_validation') {
            Response::success([
                'supported'       => false,
                'reason'          => 'rom_not_validated',
                'rom_id'          => $foundRom['profile_code'],
                'safe_mode'       => true,
                'profile_version' => $foundRom['nchanger_api'],
                'capabilities'    => array_merge($capabilities, ['restore' => false])
            ]);
            return;
        }

        // 3. Fully supported ROM
        Response::success([
            'supported'       => true,
            'rom_id'          => $foundRom['profile_code'],
            'profile_version' => $foundRom['nchanger_api'],
            'safe_mode'       => false,
            'capabilities'    => $capabilities
        ]);
    }

    /**
     * API kiểm tra Model + ROM trước khi thực hiện Change Workflow
     * Endpoint: POST /api/v1/rom/compatibility
     */
    public function compatibility(Request $request): void
    {
        $modelId = (int)$request->get('model_id', 0);
        $romId = (int)$request->get('rom_id', 0);
        $device = $request->get('device', []);

        $codename = trim($device['codename'] ?? '');
        $androidVer = trim($device['android_version'] ?? '');
        $apiLevel = (int)($device['api_level'] ?? 0);

        $db = Database::getConnection();

        // 1. Query Model & ROM
        $stmt = $db->prepare('
            SELECT 
                m.id AS model_id, m.codename,
                r.id AS rom_id, r.android_version, r.api_level, r.status AS rom_status,
                cp.id AS change_profile_id, cp.version AS profile_version, cp.workflow_code, cp.policy_json,
                cap.capabilities_json
            FROM models m
            JOIN rom_profiles r ON r.model_id = m.id
            LEFT JOIN change_profiles cp ON cp.model_id = m.id AND (cp.rom_profile_id = r.id OR cp.rom_profile_id IS NULL)
            LEFT JOIN capability_profiles cap ON cap.rom_id = r.id
            WHERE (m.id = :mid OR m.codename = :codename)
              AND (r.id = :rid OR :rid = 0)
            ORDER BY cp.id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':mid'      => $modelId,
            ':codename' => $codename,
            ':rid'      => $romId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Not found or mismatch
        if (!$row) {
            Response::success([
                'supported' => false,
                'safe_mode' => true,
                'reason'    => 'ROM_NOT_SUPPORTED'
            ]);
            return;
        }

        // If ROM is still pending validation
        if ($row['rom_status'] === 'pending_validation') {
            Response::success([
                'supported' => false,
                'safe_mode' => true,
                'reason'    => 'ROM_NOT_VALIDATED'
            ]);
            return;
        }

        $defaultCaps = [
            'backup'      => true,
            'restore'     => true,
            'recovery'    => true,
            'diagnostics' => true,
            'app_install' => true
        ];
        $capabilities = !empty($row['capabilities_json']) 
            ? array_merge($defaultCaps, json_decode($row['capabilities_json'], true))
            : $defaultCaps;

        Response::success([
            'supported' => true,
            'model' => [
                'id'       => (int)$row['model_id'],
                'codename' => $row['codename']
            ],
            'rom' => [
                'id'              => (int)$row['rom_id'],
                'android_version' => $row['android_version'],
                'api_level'       => (int)$row['api_level']
            ],
            'change_profile' => [
                'id'            => (int)($row['change_profile_id'] ?? 1),
                'version'       => $row['profile_version'] ?? '1.0.0',
                'workflow_code' => $row['workflow_code'] ?? 'NCHANGER_STANDARD_V1'
            ],
            'capabilities' => $capabilities,
            'safe_mode'    => false
        ]);
    }

    public function adminCreate(Request $request): void
    {
        $modelId = (int)$request->get('model_id', 0);
        $name = trim((string)$request->get('name', ''));
        $version = trim((string)$request->get('version', '1.0.0'));
        $androidVer = trim((string)$request->get('android_version', '12.1'));
        $apiLevel = (int)$request->get('api_level', 32);
        $recoveryType = trim((string)$request->get('recovery_type', 'recovery'));
        $systemLayout = trim((string)$request->get('system_layout', 'raw/system'));
        $vendorLayout = trim((string)$request->get('vendor_layout', 'raw/vendor'));
        $supported = (bool)$request->get('supported', true);

        if ($modelId <= 0 || empty($name)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'model_id and name are required.', 422);
        }

        $profileCode = 'rom_' . bin2hex(random_bytes(6));
        $status = $supported ? 'active' : 'pending_validation';

        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO rom_profiles (model_id, profile_code, name, version, android_version, api_level, recovery_type, system_layout, vendor_layout, status)
            VALUES (:mid, :code, :name, :ver, :aver, :api, :rec, :sys, :ven, :st)
        ');

        try {
            $stmt->execute([
                ':mid'  => $modelId,
                ':code' => $profileCode,
                ':name' => $name,
                ':ver'  => $version,
                ':aver' => $androidVer,
                ':api'  => $apiLevel,
                ':rec'  => $recoveryType,
                ':sys'  => $systemLayout,
                ':ven'  => $vendorLayout,
                ':st'   => $status
            ]);

            $romId = (int)$db->lastInsertId();

            // Default capabilities
            $capStmt = $db->prepare('INSERT INTO capability_profiles (rom_id, capabilities_json) VALUES (:rid, :caps)');
            $capStmt->execute([
                ':rid'  => $romId,
                ':caps' => json_encode([
                    'backup'      => true,
                    'restore'     => $supported,
                    'recovery'    => true,
                    'diagnostics' => true,
                    'app_install' => true
                ])
            ]);

            Response::success([
                'id'           => $romId,
                'profile_code' => $profileCode,
                'name'         => $name,
                'status'       => $status
            ], 201);
        } catch (\PDOException $e) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Failed to create ROM: ' . $e->getMessage(), 400);
        }
    }

    public function probe(Request $request): void
    {
        $payload = $request->getBody();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare('
                INSERT INTO audit_logs (action, target_type, target_id, ip_address, metadata_json)
                VALUES ("ROM_PROBE_SUBMIT", "rom_probe", :tid, :ip, :meta)
            ');
            $stmt->execute([
                ':tid'  => $payload['device']['codename'] ?? 'unknown',
                ':ip'   => $request->getClientIp(),
                ':meta' => json_encode($payload)
            ]);
        } catch (\Throwable) {}

        Response::success([
            'probed'  => true,
            'message' => 'Device and ROM probe specifications logged for validation analysis.'
        ]);
    }
}
