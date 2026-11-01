<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * DeviceModel Model
 *
 * Handles all database operations related to the models table.
 * Named DeviceModel to avoid conflict with PHP's reserved keyword/convention.
 */
class DeviceModel
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'models';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new DeviceModel model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a device model by its ID.
     *
     * @param int $id The device model ID.
     * @return array|null The device model record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a device model by its codename.
     *
     * @param string $codename The codename to search for.
     * @return array|null The device model record or null if not found.
     */
    public function findByCodename(string $codename): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE codename = :codename",
            ['codename' => $codename]
        );
    }

    /**
     * Find a device model by its model identifier.
     *
     * @param string $model The model identifier to search for.
     * @return array|null The device model record or null if not found.
     */
    public function findByModel(string $model): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE model = :model",
            ['model' => $model]
        );
    }

    /**
     * Create a new device model record.
     *
     * @param array $data The device model data. Expected keys:
     *   - brand: string
     *   - model: string
     *   - codename: string
     *   - name: string
     *   - status: string (default: 'active')
     * @return int The ID of the newly created device model.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'brand'      => $data['brand'],
            'model'      => $data['model'],
            'codename'   => $data['codename'],
            'name'       => $data['name'] ?? null,
            'status'     => $data['status'] ?? 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Update an existing device model record.
     *
     * @param int   $id   The device model ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Get a paginated list of all device models.
     *
     * @param int         $page   The page number (1-based).
     * @param int         $limit  The number of records per page.
     * @param string|null $brand  Optional brand filter.
     * @param string|null $status Optional status filter.
     * @return array The list of device model records.
     */
    public function getAll(int $page, int $limit, ?string $brand = null, ?string $status = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $conditions = [];

        $sql = "SELECT * FROM {$this->table}";

        if ($brand !== null) {
            $conditions[] = "brand = :brand";
            $params['brand'] = $brand;
        }

        if ($status !== null) {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY brand ASC, model ASC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count the total number of device models.
     *
     * @param string|null $brand  Optional brand filter.
     * @param string|null $status Optional status filter.
     * @return int The total count.
     */
    public function count(?string $brand = null, ?string $status = null): int
    {
        $params = [];
        $conditions = [];

        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if ($brand !== null) {
            $conditions[] = "brand = :brand";
            $params['brand'] = $brand;
        }

        if ($status !== null) {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        return $this->db->count($sql, $params);
    }

    /**
     * Search device models by brand, model, codename, or name.
     *
     * @param string $query The search query string.
     * @return array The matching device model records.
     */
    public function search(string $query): array
    {
        $searchTerm = '%' . $query . '%';

        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE brand LIKE :q1 OR model LIKE :q2 OR codename LIKE :q3 OR name LIKE :q4 ORDER BY brand ASC, model ASC",
            [
                'q1' => $searchTerm,
                'q2' => $searchTerm,
                'q3' => $searchTerm,
                'q4' => $searchTerm,
            ]
        );
    }

    /**
     * Get the count of active device models.
     *
     * @return int The active device model count.
     */
    public function getActiveCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'active']
        );
    }
}
