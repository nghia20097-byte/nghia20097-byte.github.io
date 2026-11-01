<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class ProfileController
{
    public function index(Request $request): void
    {
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT cp.id, cp.profile_code, cp.enabled, cp.created_at,
                   m.brand, m.model AS hardware_model, m.codename,
                   r.name AS rom_name, r.version AS rom_version
            FROM change_profiles cp
            JOIN models m ON m.id = cp.model_id
            JOIN rom_profiles r ON r.id = cp.rom_id
            ORDER BY cp.id DESC
        ');
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($profiles);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cp.*, m.brand, m.model AS hardware_model, m.codename, r.name AS rom_name, r.version AS rom_version
            FROM change_profiles cp
            JOIN models m ON m.id = cp.model_id
            JOIN rom_profiles r ON r.id = cp.rom_id
            WHERE cp.id = :id OR cp.profile_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Profile '{$id}' not found.", 404);
        }

        if (!empty($profile['operations_json'])) {
            $profile['operations'] = json_decode($profile['operations_json'], true);
            unset($profile['operations_json']);
        }

        Response::success($profile);
    }

    public function capabilities(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cap.capabilities_json, r.profile_code, r.name, r.version
            FROM capability_profiles cap
            JOIN rom_profiles r ON r.id = cap.rom_id
            WHERE r.id = :id OR r.profile_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Capabilities for ROM '{$id}' not found.", 404);
        }

        Response::success([
            'rom_id'       => $row['profile_code'],
            'rom_name'     => $row['name'],
            'rom_version'  => $row['version'],
            'capabilities' => json_decode($row['capabilities_json'] ?? '{}', true)
        ]);
    }

    public function changePolicy(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cp.profile_code, cp.operations_json, cp.enabled
            FROM change_profiles cp
            WHERE cp.id = :id OR cp.profile_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Change policy '{$id}' not found.", 404);
        }

        Response::success([
            'profile_id' => $row['profile_code'],
            'enabled'    => (bool)$row['enabled'],
            'operations' => json_decode($row['operations_json'] ?? '{}', true)
        ]);
    }
}
