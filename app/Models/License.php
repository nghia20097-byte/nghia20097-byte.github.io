<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * License Model
 *
 * Handles all database operations related to the licenses table.
 * License key format: NC-XXXX-XXXX-XXXX
 */
class License
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'licenses';

    /**
     * @var string The pivot table for license-device relationships.
     */
    protected string $pivotTable = 'license_devices';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new License model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate a unique license key in the format NC-XXXX-XXXX-XXXX.
     *
     * @return string The generated license key.
     */
    public static function generateKey(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $segments = [];

        for ($i = 0; $i < 3; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }

        return 'NC-' . implode('-', $segments);
    }

    /**
     * Find a license by its ID.
     *
     * @param int $id The license ID.
     * @return array|null The license record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a license by its key.
     *
     * @param string $key The license key.
     * @return array|null The license record or null if not found.
     */
    public function findByKey(string $key): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE license_key = :key",
            ['key' => $key]
        );
    }

    /**
     * Create a new license record.
     *
     * @param array $data The license data. Expected keys:
     *   - license_key: string (auto-generated if not provided)
     *   - plan: string
     *   - max_devices: int
     *   - expires_at: string (ISO 8601)
     *   - user_id: int|null
     * @return int The ID of the newly created license.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'license_key'  => $data['license_key'] ?? self::generateKey(),
            'plan'         => $data['plan'] ?? 'basic',
            'status'       => $data['status'] ?? 'active',
            'max_devices'  => $data['max_devices'] ?? 1,
            'user_id'      => $data['user_id'] ?? null,
            'expires_at'   => $data['expires_at'] ?? null,
            'activated_at' => $data['activated_at'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    /**
     * Update an existing license record.
     *
     * @param int   $id   The license ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Activate a license.
     *
     * @param int $id The license ID.
     * @return bool True if activated successfully.
     */
    public function activate(int $id): bool
    {
        return $this->db->update(
            $this->table,
            [
                'status'       => 'active',
                'activated_at' => date('c'),
                'updated_at'   => date('c'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Disable a license.
     *
     * @param int $id The license ID.
     * @return bool True if disabled successfully.
     */
    public function disable(int $id): bool
    {
        return $this->db->update(
            $this->table,
            [
                'status'     => 'disabled',
                'updated_at' => date('c'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Extend a license's expiry date.
     *
     * @param int    $id        The license ID.
     * @param string $newExpiry The new expiry date in ISO 8601 format.
     * @return bool True if extended successfully.
     */
    public function extend(int $id, string $newExpiry): bool
    {
        return $this->db->update(
            $this->table,
            [
                'expires_at' => $newExpiry,
                'updated_at' => date('c'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Get a paginated list of all licenses.
     *
     * @param int         $page   The page number (1-based).
     * @param int         $limit  The number of records per page.
     * @param string|null $status Optional status filter.
     * @param string|null $plan   Optional plan filter.
     * @return array The list of license records.
     */
    public function getAll(int $page, int $limit, ?string $status = null, ?string $plan = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $conditions = [];

        $sql = "SELECT * FROM {$this->table}";

        if ($status !== null) {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }

        if ($plan !== null) {
            $conditions[] = "plan = :plan";
            $params['plan'] = $plan;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count the total number of licenses.
     *
     * @param string|null $status Optional status filter.
     * @return int The total count.
     */
    public function count(?string $status = null): int
    {
        $params = [];
        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if ($status !== null) {
            $sql .= " WHERE status = :status";
            $params['status'] = $status;
        }

        return $this->db->count($sql, $params);
    }

    /**
     * Get the number of devices associated with a license.
     *
     * @param int $licenseId The license ID.
     * @return int The device count.
     */
    public function getDeviceCount(int $licenseId): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->pivotTable} WHERE license_id = :license_id",
            ['license_id' => $licenseId]
        );
    }

    /**
     * Associate a device with a license.
     *
     * @param int $licenseId The license ID.
     * @param int $deviceId  The device ID.
     * @return int The ID of the pivot record.
     */
    public function addDevice(int $licenseId, int $deviceId): int
    {
        return $this->db->insert($this->pivotTable, [
            'license_id' => $licenseId,
            'device_id'  => $deviceId,
            'created_at' => date('c'),
        ]);
    }

    /**
     * Remove a device association from a license.
     *
     * @param int $licenseId The license ID.
     * @param int $deviceId  The device ID.
     * @return bool True if the device was removed.
     */
    public function removeDevice(int $licenseId, int $deviceId): bool
    {
        return $this->db->delete(
            $this->pivotTable,
            'license_id = :license_id AND device_id = :device_id',
            ['license_id' => $licenseId, 'device_id' => $deviceId]
        );
    }

    /**
     * Get the count of active licenses.
     *
     * @return int The active license count.
     */
    public function getActiveCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'active']
        );
    }

    /**
     * Get the count of expired licenses.
     *
     * @return int The expired license count.
     */
    public function getExpiredCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'expired']
        );
    }

    /**
     * Get the count of disabled licenses.
     *
     * @return int The disabled license count.
     */
    public function getDisabledCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'disabled']
        );
    }
}
