<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * RomProfile Model
 *
 * Handles all database operations related to the rom_profiles table.
 */
class RomProfile
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'rom_profiles';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new RomProfile model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a ROM profile by its ID.
     *
     * @param int $id The ROM profile ID.
     * @return array|null The ROM profile record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find ROM profiles by model ID.
     *
     * @param int $modelId The device model ID.
     * @return array The list of ROM profile records for the given model.
     */
    public function findByModelId(int $modelId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE model_id = :model_id ORDER BY android_version DESC",
            ['model_id' => $modelId]
        );
    }

    /**
     * Find a ROM profile by model ID and Android version.
     *
     * @param int    $modelId        The device model ID.
     * @param string $androidVersion The Android version string.
     * @return array|null The ROM profile record or null if not found.
     */
    public function findByModelAndAndroid(int $modelId, string $androidVersion): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE model_id = :model_id AND android_version = :android_version",
            [
                'model_id'        => $modelId,
                'android_version' => $androidVersion,
            ]
        );
    }

    /**
     * Create a new ROM profile record.
     *
     * @param array $data The ROM profile data. Expected keys:
     *   - model_id: int
     *   - android_version: string
     *   - rom_name: string
     *   - rom_version: string|null
     *   - build_fingerprint: string|null
     *   - status: string (default: 'active')
     * @return int The ID of the newly created ROM profile.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'model_id'          => $data['model_id'],
            'android_version'   => $data['android_version'],
            'rom_name'          => $data['rom_name'] ?? null,
            'rom_version'       => $data['rom_version'] ?? null,
            'build_fingerprint' => $data['build_fingerprint'] ?? null,
            'status'            => $data['status'] ?? 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    /**
     * Update an existing ROM profile record.
     *
     * @param int   $id   The ROM profile ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Get a paginated list of all ROM profiles.
     *
     * @param int         $page   The page number (1-based).
     * @param int         $limit  The number of records per page.
     * @param string|null $status Optional status filter.
     * @return array The list of ROM profile records.
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
     * Count the total number of ROM profiles.
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
     * Get the count of active ROM profiles.
     *
     * @return int The active ROM profile count.
     */
    public function getActiveCount(): int
    {
        return $this->db->count(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status",
            ['status' => 'active']
        );
    }
}
