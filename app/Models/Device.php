<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Device Model
 *
 * Handles all database operations related to the devices table.
 * Represents registered client devices.
 */
class Device
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'devices';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new Device model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a device by its ID.
     *
     * @param int $id The device ID.
     * @return array|null The device record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a device by its UUID.
     *
     * @param string $uuid The device UUID.
     * @return array|null The device record or null if not found.
     */
    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE uuid = :uuid",
            ['uuid' => $uuid]
        );
    }

    /**
     * Find a device by its client ID.
     *
     * @param string $clientId The client-provided device identifier.
     * @return array|null The device record or null if not found.
     */
    public function findByClientId(string $clientId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE client_id = :client_id",
            ['client_id' => $clientId]
        );
    }

    /**
     * Create a new device record.
     *
     * @param array $data The device data. Expected keys:
     *   - uuid: string
     *   - client_id: string
     *   - model_id: int|null
     *   - platform: string
     *   - os_version: string|null
     *   - app_version: string|null
     *   - device_info: string|null (JSON)
     * @return int The ID of the newly created device.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'uuid'         => $data['uuid'],
            'client_id'    => $data['client_id'],
            'model_id'     => $data['model_id'] ?? null,
            'platform'     => $data['platform'] ?? 'android',
            'os_version'   => $data['os_version'] ?? null,
            'app_version'  => $data['app_version'] ?? null,
            'device_info'  => $data['device_info'] ?? null,
            'status'       => $data['status'] ?? 'online',
            'last_seen_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    /**
     * Update an existing device record.
     *
     * @param int   $id   The device ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Update the status of a device.
     *
     * @param int    $id     The device ID.
     * @param string $status The new status (e.g., 'online', 'offline', 'suspended').
     * @return bool True if updated successfully.
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->db->update(
            $this->table,
            [
                'status'     => $status,
                'updated_at' => date('c'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Update the last seen timestamp for a device.
     *
     * @param int $id The device ID.
     * @return bool True if updated successfully.
     */
    public function updateLastSeen(int $id): bool
    {
        return $this->db->update(
            $this->table,
            [
                'last_seen_at' => date('c'),
                'updated_at'   => date('c'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Get a paginated list of all devices.
     *
     * @param int         $page     The page number (1-based).
     * @param int         $limit    The number of records per page.
     * @param string|null $status   Optional status filter.
     * @param string|null $platform Optional platform filter.
     * @return array The list of device records.
     */
    public function getAll(int $page, int $limit, ?string $status = null, ?string $platform = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $conditions = [];

        $sql = "SELECT * FROM {$this->table}";

        if ($status !== null) {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }

        if ($platform !== null) {
            $conditions[] = "platform = :platform";
            $params['platform'] = $platform;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY last_seen_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count the total number of devices.
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
     * Get the count of online devices.
     *
     * @return int The online device count.
     */
    public function getOnlineCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'online']
        );
    }

    /**
     * Get the count of devices seen today.
     *
     * @return int The count of devices seen today.
     */
    public function getTodayCount(): int
    {
        $today = date('Y-m-d');

        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE DATE(last_seen_at) = :today",
            ['today' => $today]
        );
    }

    /**
     * Get all currently online devices.
     *
     * @return array The list of online device records.
     */
    public function getOnlineDevices(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE status = :status ORDER BY last_seen_at DESC",
            ['status' => 'online']
        );
    }
}
