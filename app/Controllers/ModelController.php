<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class ModelController
{
    public function index(Request $request): void
    {
        $db = Database::getConnection();

        $brand = $request->getQuery('brand');
        $codename = $request->getQuery('codename');
        $status = $request->getQuery('status');
        $page = max(1, (int)$request->getQuery('page', 1));
        $limit = min(100, max(1, (int)$request->getQuery('limit', 50)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($brand)) {
            $where[] = 'b.name = :brand';
            $params[':brand'] = $brand;
        }

        if (!empty($codename)) {
            $where[] = 'm.codename = :codename';
            $params[':codename'] = $codename;
        }

        if (!empty($status)) {
            $where[] = 'm.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM models m 
            JOIN brands b ON b.id = m.brand_id 
            $whereSql
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch models
        $sql = "
            SELECT 
                m.id, 
                b.name AS brand, 
                m.brand_id, 
                m.model_code, 
                m.marketing_name, 
                m.codename, 
                m.soc, 
                m.architecture, 
                m.android_min, 
                m.android_max, 
                m.status, 
                m.created_at, 
                m.updated_at
            FROM models m
            JOIN brands b ON b.id = m.brand_id
            $whereSql
            ORDER BY b.name ASC, m.model_code ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($models, 200, [
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT m.*, b.name AS brand
            FROM models m
            JOIN brands b ON b.id = m.brand_id
            WHERE m.id = :id OR m.codename = :id OR m.model_code = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$model) {
            Response::error(Response::ERR_MODEL_NOT_FOUND, "Model ID '{$id}' not found.", 404);
        }

        Response::success($model);
    }

    public function create(Request $request): void
    {
        $brandId = (int)$request->get('brand_id', 0);
        $modelCode = trim((string)$request->get('model_code', ''));
        $name = trim((string)$request->get('marketing_name', ''));
        $codename = trim((string)$request->get('codename', ''));
        $soc = trim((string)$request->get('soc', ''));
        $arch = trim((string)$request->get('architecture', 'arm64'));
        $androidMin = trim((string)$request->get('android_min', ''));
        $androidMax = trim((string)$request->get('android_max', ''));
        $status = trim((string)$request->get('status', 'active'));

        if ($brandId <= 0 || empty($modelCode) || empty($name)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'brand_id, model_code, and marketing_name are required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO models (brand_id, model_code, marketing_name, codename, soc, architecture, android_min, android_max, status)
            VALUES (:bid, :code, :name, :codename, :soc, :arch, :amin, :amax, :st)
        ');

        try {
            $stmt->execute([
                ':bid'      => $brandId,
                ':code'     => $modelCode,
                ':name'     => $name,
                ':codename' => !empty($codename) ? $codename : null,
                ':soc'      => !empty($soc) ? $soc : null,
                ':arch'     => !empty($arch) ? $arch : 'arm64',
                ':amin'     => !empty($androidMin) ? $androidMin : null,
                ':amax'     => !empty($androidMax) ? $androidMax : null,
                ':st'       => in_array($status, ['active', 'disabled']) ? $status : 'active'
            ]);

            $newId = (int)$db->lastInsertId();
            Response::success([
                'id'             => $newId,
                'brand_id'       => $brandId,
                'model_code'     => $modelCode,
                'marketing_name' => $name,
                'codename'       => $codename,
                'status'         => $status
            ], 201);
        } catch (\PDOException $e) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Could not create model: ' . $e->getMessage(), 400);
        }
    }

    public function update(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        // Check exists
        $chk = $db->prepare('SELECT id FROM models WHERE id = :id LIMIT 1');
        $chk->execute([':id' => $id]);
        if (!$chk->fetchColumn()) {
            Response::error(Response::ERR_MODEL_NOT_FOUND, "Model ID '{$id}' not found.", 404);
        }

        $body = $request->getBody();
        $allowedFields = ['brand_id', 'model_code', 'marketing_name', 'codename', 'soc', 'architecture', 'android_min', 'android_max', 'status'];
        $updates = [];
        $binds = [':id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $body)) {
                $updates[] = "`$field` = :$field";
                $binds[":$field"] = $body[$field];
            }
        }

        if (empty($updates)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'No fields provided for update.', 422);
        }

        $sql = 'UPDATE models SET ' . implode(', ', $updates) . ' WHERE id = :id';
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

        $stmt = $db->prepare('UPDATE models SET status = "disabled" WHERE id = :id');
        $stmt->execute([':id' => $id]);

        Response::success([
            'id'       => (int)$id,
            'status'   => 'disabled',
            'disabled' => true
        ]);
    }

    public function roms(Request $request, array $params): void
    {
        $this->romProfile($request, $params);
    }

    public function romProfile(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT r.* 
            FROM rom_profiles r
            JOIN models m ON m.id = r.model_id
            WHERE (m.id = :id OR m.codename = :id OR m.model_code = :id) AND r.status != "disabled"
            ORDER BY r.id DESC
        ');
        $stmt->execute([':id' => $id]);
        $roms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roms as &$rom) {
            if (!empty($rom['manifest_json'])) {
                $rom['manifest'] = json_decode($rom['manifest_json'], true);
                unset($rom['manifest_json']);
            }
        }

        Response::success($roms);
    }

    public function capabilities(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cp.capabilities_json, r.profile_code, r.name, r.version
            FROM capability_profiles cp
            JOIN rom_profiles r ON r.id = cp.rom_id
            JOIN models m ON m.id = r.model_id
            WHERE (m.id = :id OR m.codename = :id OR m.model_code = :id) AND r.status != "disabled"
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$res) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Capabilities not found for model '{$id}'.", 404);
        }

        Response::success([
            'rom_profile'  => $res['profile_code'],
            'rom_name'     => $res['name'],
            'capabilities' => json_decode($res['capabilities_json'], true)
        ]);
    }

    public function changeProfile(Request $request, array $params): void
    {
        $id = $params['id'] ?? null;
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT 
                m.id AS model_id, b.name AS brand, m.model_code, m.codename,
                r.id AS rom_id, r.name AS rom_name, r.android_version, r.api_level,
                cp.id AS change_profile_id, cp.name AS profile_name, cp.version AS profile_version, 
                cp.workflow_code, cp.status AS profile_status, cp.policy_json
            FROM change_profiles cp
            JOIN models m ON m.id = cp.model_id
            JOIN brands b ON b.id = m.brand_id
            LEFT JOIN rom_profiles r ON r.id = cp.rom_profile_id
            WHERE (m.id = :id OR m.codename = :id OR m.model_code = :id) AND cp.status = "active"
            ORDER BY cp.id DESC
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_PROFILE_NOT_FOUND, "Active change profile not found for model '{$id}'.", 404);
        }

        Response::success([
            'model' => [
                'id'         => (int)$row['model_id'],
                'brand'      => $row['brand'],
                'model_code' => $row['model_code'],
                'codename'   => $row['codename']
            ],
            'rom' => [
                'id'              => (int)($row['rom_id'] ?? 0),
                'name'            => $row['rom_name'] ?? 'Generic baseline',
                'android_version' => $row['android_version'] ?? 'N/A',
                'api_level'       => (int)($row['api_level'] ?? 0)
            ],
            'change_profile' => [
                'id'            => (int)$row['change_profile_id'],
                'name'          => $row['profile_name'],
                'version'       => $row['profile_version'],
                'workflow_code' => $row['workflow_code'],
                'status'        => $row['profile_status'],
                'policy'        => json_decode($row['policy_json'] ?? '{}', true)
            ]
        ]);
    }
}
