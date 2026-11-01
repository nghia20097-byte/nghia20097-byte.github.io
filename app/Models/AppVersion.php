<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * AppVersion Model
 *
 * Handles all database operations related to the app_versions table.
 * Tracks application versions for different platforms.
 */
class AppVersion
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'app_versions';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new AppVersion model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find an app version by its ID.
     *
     * @param int $id The app version ID.
     * @return array|null The app version record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Get the latest app version for a specific platform.
     *
     * @param string $platform The platform identifier (e.g., 'android', 'ios').
     * @return array|null The latest version record or null if none found.
     */
    public function getLatest(string $platform): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE platform = :platform ORDER BY created_at DESC LIMIT 1",
            ['platform' => $platform]
        );
    }

    /**
     * Create a new app version record.
     *
     * @param array $data The app version data. Expected keys:
     *   - platform: string
     *   - version: string
     *   - version_code: int
     *   - release_notes: string|null
     *   - download_url: string|null
     *   - min_sdk_version: int|null
     *   - is_mandatory: bool
     * @return int The ID of the newly created app version.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'platform'        => $data['platform'],
            'version'         => $data['version'],
            'version_code'    => $data['version_code'] ?? 0,
            'release_notes'   => $data['release_notes'] ?? null,
            'download_url'    => $data['download_url'] ?? null,
            'min_sdk_version' => $data['min_sdk_version'] ?? null,
            'is_mandatory'    => $data['is_mandatory'] ?? false,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /**
     * Get all app versions for a specific platform.
     *
     * @param string $platform The platform identifier.
     * @return array The list of app version records, sorted newest first.
     */
    public function getAll(string $platform): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE platform = :platform ORDER BY created_at DESC",
            ['platform' => $platform]
        );
    }
}
