<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * User Model
 *
 * Handles all database operations related to the users table.
 */
class User
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'users';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new User model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a user by their ID.
     *
     * @param int $id The user ID.
     * @return array|null The user record or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Find a user by their username.
     *
     * @param string $username The username to search for.
     * @return array|null The user record or null if not found.
     */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE username = :username",
            ['username' => $username]
        );
    }

    /**
     * Find a user by their email address.
     *
     * @param string $email The email address to search for.
     * @return array|null The user record or null if not found.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE email = :email",
            ['email' => $email]
        );
    }

    /**
     * Create a new user record.
     *
     * @param array $data The user data. Expected keys:
     *   - username: string
     *   - email: string
     *   - password: string (plain text, will be hashed)
     *   - role: string (default: 'user')
     *   - display_name: string|null
     * @return int The ID of the newly created user.
     */
    public function create(array $data): int
    {
        $now = date('c');

        return $this->db->insert($this->table, [
            'username'     => $data['username'],
            'email'        => $data['email'],
            'password'     => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'         => $data['role'] ?? 'user',
            'display_name' => $data['display_name'] ?? null,
            'status'       => $data['status'] ?? 'active',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    /**
     * Update an existing user record.
     *
     * @param int   $id   The user ID to update.
     * @param array $data The fields to update.
     * @return bool True if the update was successful.
     */
    public function update(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $data['updated_at'] = date('c');

        return $this->db->update($this->table, $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Update the last login timestamp for a user.
     *
     * @param int $id The user ID.
     * @return bool True if the update was successful.
     */
    public function updateLastLogin(int $id): bool
    {
        return $this->db->update(
            $this->table,
            ['last_login_at' => date('c')],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Get a paginated list of all users.
     *
     * @param int         $page  The page number (1-based).
     * @param int         $limit The number of records per page.
     * @param string|null $role  Optional role filter.
     * @return array The list of user records.
     */
    public function getAll(int $page, int $limit, ?string $role = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];

        $sql = "SELECT id, username, email, role, display_name, status, last_login_at, created_at, updated_at FROM {$this->table}";

        if ($role !== null) {
            $sql .= " WHERE role = :role";
            $params['role'] = $role;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count the total number of users.
     *
     * @param string|null $role Optional role filter.
     * @return int The total count.
     */
    public function count(?string $role = null): int
    {
        $params = [];
        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if ($role !== null) {
            $sql .= " WHERE role = :role";
            $params['role'] = $role;
        }

        return $this->db->count($sql, $params);
    }
}
