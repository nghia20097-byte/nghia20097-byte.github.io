<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Backup Model
 *
 * Handles all database operations related to the backups table.
 * Represents device backup records.
 */
class Backup
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'backups';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new Backup model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a backup by its ID.
     *
     * @param int $id The backup ID.
     * @return array|null The backup record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a backup by its UUID.
     *
     * @param string $uuid The backup UUID.
     * @return array|null The backup record or null if not found.
     */
    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE uuid = :uuid",
            ['uuid' => $uuid]
        );
    }

    /**
     * Find all backups for a specific device.
     *
     * @param int $deviceId The device ID.
     * @return array The list of backup records for the given device.
     */
    public function findByDeviceId(int $deviceId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE device_id = :device_id ORDER BY created_at DESC",
            ['device_id' => $deviceId]
        );
    }

    /**
     * Create a new backup record.
     *
     * @param array $data The backup data. Expected keys:
     *   - uuid: string
     *   - device_id: int
     *   - type: string (e.g., 'full', 'partial')
     *   - file_path: string|null
     *   - file_size: int|null
     *   - checksum: string|null
     *   - metadata: string|null (JSON)
     * @return int The ID of the newly created backup.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'uuid'       => $data['uuid'],
            'device_id'  => $data['device_id'],
            'type'       => $data['type'] ?? 'full',
            'status'     => $data['status'] ?? 'pending',
            'file_path'  => $data['file_path'] ?? null,
            'file_size'  => $data['file_size'] ?? null,
            'checksum'   => $data['checksum'] ?? null,
            'metadata'   => $data['metadata'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Update an existing backup record.
     *
     * @param int   $id   The backup ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Update the status of a backup.
     *
     * @param int    $id     The backup ID.
     * @param string $status The new status (e.g., 'pending', 'completed', 'failed').
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
     * Delete a backup record.
     *
     * @param int $id The backup ID to delete.
     * @return bool True if the deletion was successful.
     */
    public function delete(int $id): bool
    {
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]);
    }

    /**
     * Get a paginated list of all backups.
     *
     * @param int         $page   The page number (1-based).
     * @param int         $limit  The number of records per page.
     * @param string|null $status Optional status filter.
     * @return array The list of backup records.
     */
    public function getAll(int $page, int $limit, ?string $status = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];

        $sql = "SELECT * FROM {$this->table}";

        if ($status !== null) {
            $sql .= " WHERE status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count the total number of backups.
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
}
