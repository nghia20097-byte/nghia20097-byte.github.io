<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * SystemSetting Model
 *
 * Handles all database operations related to the system_settings table.
 * Provides a key-value store for system configuration.
 */
class SystemSetting
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'system_settings';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new SystemSetting model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get a setting value by its key.
     *
     * @param string $key     The setting key.
     * @param mixed  $default The default value to return if the key doesn't exist.
     * @return mixed The setting value, or the default if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetch(
            "SELECT setting_value FROM {$this->table} WHERE setting_key = :key",
            ['key' => $key]
        );

        if ($row === null) {
            return $default;
        }

        // Attempt to decode JSON; return raw string if not JSON
        $decoded = json_decode($row['setting_value'], true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
    }

    /**
     * Set a setting value by its key.
     *
     * Creates the setting if it doesn't exist, or updates it if it does.
     *
     * @param string $key   The setting key.
     * @param mixed  $value The setting value (will be JSON-encoded if not a string).
     * @return bool True if the operation was successful.
     */
    public function set(string $key, mixed $value): bool
    {
        $encoded = is_string($value) ? $value : json_encode($value);
        $now = date('c');

        $existing = $this->db->fetch(
            "SELECT id FROM {$this->table} WHERE setting_key = :key",
            ['key' => $key]
        );

        if ($existing !== null) {
            return $this->db->update(
                $this->table,
                [
                    'setting_value' => $encoded,
                    'updated_at'    => $now,
                ],
                'id = :id',
                ['id' => $existing['id']]
            );
        }

        $this->db->insert($this->table, [
            'setting_key'   => $key,
            'setting_value' => $encoded,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return true;
    }

    /**
     * Get all system settings.
     *
     * @return array An associative array of key => value pairs.
     */
    public function getAll(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM {$this->table} ORDER BY setting_key ASC"
        );

        $settings = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Get multiple settings by their keys.
     *
     * @param array $keys The list of setting keys to retrieve.
     * @return array An associative array of key => value pairs for found settings.
     */
    public function getMultiple(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($keys as $i => $key) {
            $placeholder = ":key_{$i}";
            $placeholders[] = $placeholder;
            $params["key_{$i}"] = $key;
        }

        $inClause = implode(', ', $placeholders);
        $rows = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM {$this->table} WHERE setting_key IN ({$inClause})",
            $params
        );

        $settings = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
        }

        return $settings;
    }
}
