<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * RestoreJob Model
 *
 * Handles all database operations related to the restore_jobs table.
 * Represents device restore job records.
 */
class RestoreJob
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'restore_jobs';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new RestoreJob model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a restore job by its ID.
     *
     * @param int $id The restore job ID.
     * @return array|null The restore job record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a restore job by its UUID.
     *
     * @param string $uuid The restore job UUID.
     * @return array|null The restore job record or null if not found.
     */
    public function findByUuid(string $uuid): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE uuid = :uuid",
            ['uuid' => $uuid]
        );
    }

    /**
     * Create a new restore job record.
     *
     * @param array $data The restore job data. Expected keys:
     *   - uuid: string
     *   - device_id: int
     *   - backup_id: int
     *   - type: string (e.g., 'full', 'partial')
     *   - options: string|null (JSON)
     * @return int The ID of the newly created restore job.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'uuid'       => $data['uuid'],
            'device_id'  => $data['device_id'],
            'backup_id'  => $data['backup_id'],
            'type'       => $data['type'] ?? 'full',
            'status'     => $data['status'] ?? 'pending',
            'progress'   => $data['progress'] ?? 0,
            'error'      => $data['error'] ?? null,
            'options'    => $data['options'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Update an existing restore job record.
     *
     * @param int   $id   The restore job ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Update the status of a restore job with optional progress and error.
     *
     * @param int         $id       The restore job ID.
     * @param string      $status   The new status (e.g., 'pending', 'running', 'completed', 'failed').
     * @param int|null    $progress The current progress percentage (0-100).
     * @param string|null $error    The error message if the job failed.
     * @return bool True if updated successfully.
     */
    public function updateStatus(int $id, string $status, ?int $progress = null, ?string $error = null): bool
    {
        $data = [
            'status'     => $status,
            'updated_at' => date('c'),
        ];

        if ($progress !== null) {
            $data['progress'] = $progress;
        }

        if ($error !== null) {
            $data['error'] = $error;
        }

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Get a paginated list of all restore jobs.
     *
     * @param int         $page   The page number (1-based).
     * @param int         $limit  The number of records per page.
     * @param string|null $status Optional status filter.
     * @return array The list of restore job records.
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
}
