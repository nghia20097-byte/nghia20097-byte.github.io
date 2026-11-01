<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * ChangeProfile Model
 *
 * Handles all database operations related to the change_profiles table.
 * Defines the change policy/configuration for a specific model and ROM combination.
 */
class ChangeProfile
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'change_profiles';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new ChangeProfile model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a change profile by its ID.
     *
     * @param int $id The change profile ID.
     * @return array|null The change profile record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a change profile by model ID and ROM profile ID.
     *
     * @param int $modelId The device model ID.
     * @param int $romId   The ROM profile ID.
     * @return array|null The change profile record or null if not found.
     */
    public function findByModelAndRom(int $modelId, int $romId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE model_id = :model_id AND rom_id = :rom_id",
            [
                'model_id' => $modelId,
                'rom_id'   => $romId,
            ]
        );
    }

    /**
     * Create a new change profile record.
     *
     * @param array $data The change profile data. Expected keys:
     *   - model_id: int
     *   - rom_id: int
     *   - change_method: string
     *   - requires_reboot: bool
     *   - config_json: string|null (JSON string for extended configuration)
     * @return int The ID of the newly created change profile.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'model_id'        => $data['model_id'],
            'rom_id'          => $data['rom_id'],
            'change_method'   => $data['change_method'] ?? 'default',
            'requires_reboot' => $data['requires_reboot'] ?? false,
            'config_json'     => $data['config_json'] ?? null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /**
     * Update an existing change profile record.
     *
     * @param int   $id   The change profile ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Get a paginated list of all change profiles.
     *
     * @param int $page  The page number (1-based).
     * @param int $limit The number of records per page.
     * @return array The list of change profile records.
     */
    public function getAll(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            [
                'limit'  => $limit,
                'offset' => $offset,
            ]
        );
    }
}
