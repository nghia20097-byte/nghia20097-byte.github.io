<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * CapabilityProfile Model
 *
 * Handles all database operations related to the capability_profiles table.
 * Defines what capabilities are available for a specific ROM profile.
 */
class CapabilityProfile
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'capability_profiles';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new CapabilityProfile model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a capability profile by its ID.
     *
     * @param int $id The capability profile ID.
     * @return array|null The capability profile record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find capability profiles by ROM profile ID.
     *
     * @param int $romId The ROM profile ID.
     * @return array|null The capability profile record or null if not found.
     */
    public function findByRomId(int $romId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE rom_id = :rom_id",
            ['rom_id' => $romId]
        );
    }

    /**
     * Create a new capability profile record.
     *
     * @param array $data The capability profile data. Expected keys:
     *   - rom_id: int
     *   - can_change_imei: bool
     *   - can_change_serial: bool
     *   - can_change_mac: bool
     *   - can_change_bluetooth: bool
     *   - can_change_build: bool
     *   - can_change_sim: bool
     *   - capabilities_json: string|null (JSON string for extended capabilities)
     * @return int The ID of the newly created capability profile.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'rom_id'              => $data['rom_id'],
            'can_change_imei'     => $data['can_change_imei'] ?? false,
            'can_change_serial'   => $data['can_change_serial'] ?? false,
            'can_change_mac'      => $data['can_change_mac'] ?? false,
            'can_change_bluetooth' => $data['can_change_bluetooth'] ?? false,
            'can_change_build'    => $data['can_change_build'] ?? false,
            'can_change_sim'      => $data['can_change_sim'] ?? false,
            'capabilities_json'   => $data['capabilities_json'] ?? null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    /**
     * Update an existing capability profile record.
     *
     * @param int   $id   The capability profile ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }
}
