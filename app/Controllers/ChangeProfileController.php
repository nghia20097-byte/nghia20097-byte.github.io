<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class ChangeProfileController
{
    public function index(Request $request): void
    {
        $db = Database::getConnection();
        $modelId = $request->getQuery('model_id');
        $status = $request->getQuery('status');

        $where = [];
        $params = [];

        if (!empty($modelId)) {
            $where[] = 'cp.model_id = :mid';
            $params[':mid'] = (int)$modelId;
        }

        if (!empty($status)) {
            $where[] = 'cp.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT 
                cp.id, cp.model_id, cp.rom_profile_id, cp.name, cp.version, cp.workflow_code, 
                cp.status, cp.policy_json, cp.created_at, cp.updated_at,
                m.model_code, m.marketing_name, m.codename,
                r.name AS rom_name, r.version AS rom_version
            FROM change_profiles cp
            JOIN models m ON m.id = cp.model_id
            LEFT JOIN rom_profiles r ON r.id = cp.rom_profile_id
            $whereSql
            ORDER BY cp.id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($profiles as &$p) {
            $p['policy'] = json_decode($p['policy_json'] ?? '{}', true);
            unset($p['policy_json']);
        }

        Response::success($profiles);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cp.*, m.model_code, m.marketing_name, m.codename, r.name AS rom_name
            FROM change_profiles cp
            JOIN models m ON m.id = cp.model_id
            LEFT JOIN rom_profiles r ON r.id = cp.rom_profile_id
            WHERE cp.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Change profile '{$id}' not found.", 404);
        }

        $row['policy'] = json_decode($row['policy_json'] ?? '{}', true);
        unset($row['policy_json']);

        Response::success($row);
    }

    public function create(Request $request): void
    {
        $modelId = (int)$request->get('model_id', 0);
        $romProfileId = $request->get('rom_profile_id') ? (int)$request->get('rom_profile_id') : null;
        $name = trim((string)$request->get('name', ''));
        $version = trim((string)$request->get('version', '1.0.0'));
        $workflowCode = trim((string)$request->get('workflow_code', 'NCHANGER_STANDARD_V1'));
        $policy = $request->get('policy', $request->get('policy_json', []));

        if ($modelId <= 0 || empty($name) || empty($workflowCode)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'model_id, name, and workflow_code are required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO change_profiles (model_id, rom_profile_id, name, version, workflow_code, policy_json, status)
            VALUES (:mid, :rid, :name, :ver, :wf, :policy, "active")
        ');

        try {
            $stmt->execute([
                ':mid'    => $modelId,
                ':rid'    => $romProfileId,
                ':name'   => $name,
                ':ver'    => $version,
                ':wf'     => $workflowCode,
                ':policy' => json_encode($policy)
            ]);

            $newId = (int)$db->lastInsertId();
            Response::success([
                'id'            => $newId,
                'model_id'      => $modelId,
                'name'          => $name,
                'version'       => $version,
                'workflow_code' => $workflowCode,
                'status'        => 'active'
            ], 201);
        } catch (\PDOException $e) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Could not create change profile: ' . $e->getMessage(), 400);
        }
    }

    public function update(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $body = $request->getBody();
        $updates = [];
        $binds = [':id' => $id];

        if (isset($body['name'])) {
            $updates[] = '`name` = :name';
            $binds[':name'] = $body['name'];
        }
        if (isset($body['version'])) {
            $updates[] = '`version` = :version';
            $binds[':version'] = $body['version'];
        }
        if (isset($body['workflow_code'])) {
            $updates[] = '`workflow_code` = :workflow_code';
            $binds[':workflow_code'] = $body['workflow_code'];
        }
        if (isset($body['status'])) {
            $updates[] = '`status` = :status';
            $binds[':status'] = $body['status'];
        }
        if (isset($body['policy']) || isset($body['policy_json'])) {
            $updates[] = '`policy_json` = :policy';
            $binds[':policy'] = json_encode($body['policy'] ?? $body['policy_json']);
        }

        if (empty($updates)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'No fields provided for update.', 422);
        }

        $sql = 'UPDATE change_profiles SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($binds);

        Response::success([
            'id'      => (int)$id,
            'updated' => true
        ]);
    }

    public function delete(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('UPDATE change_profiles SET status = "disabled" WHERE id = :id');
        $stmt->execute([':id' => $id]);

        Response::success([
            'id'       => (int)$id,
            'status'   => 'disabled',
            'disabled' => true
        ]);
    }
}
