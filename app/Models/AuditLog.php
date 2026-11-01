<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * AuditLog Model
 *
 * Handles all database operations related to the audit_logs table.
 * Provides an immutable audit trail for system actions.
 */
class AuditLog
{
    /**
     * @var string The database table name.
     */
    protected string $table = 'audit_logs';

    /**
     * @var Database The database instance.
     */
    protected Database $db;

    /**
     * Create a new AuditLog model instance.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new audit log entry.
     *
     * @param array $data The audit log data. Expected keys:
     *   - user_id: int|null
     *   - device_id: int|null
     *   - action: string
     *   - target_type: string|null
     *   - target_id: int|null
     *   - ip_address: string|null
     *   - user_agent: string|null
     *   - metadata: string|null (JSON)
     * @return int The ID of the newly created audit log entry.
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, [
            'user_id'     => $data['user_id'] ?? null,
            'device_id'   => $data['device_id'] ?? null,
            'action'      => $data['action'],
            'target_type' => $data['target_type'] ?? null,
            'target_id'   => $data['target_id'] ?? null,
            'ip_address'  => $data['ip_address'] ?? null,
            'user_agent'  => $data['user_agent'] ?? null,
            'metadata'    => $data['metadata'] ?? null,
            'created_at'  => date('c'),
        ]);
    }

    /**
     * Get a paginated list of all audit log entries.
     *
     * @param int      $page   The page number (1-based).
     * @param int      $limit  The number of records per page.
     * @param int|null $userId Optional user ID filter.
     * @param string|null $action Optional action filter.
     * @return array The list of audit log records.
     */
    public function getAll(int $page, int $limit, ?int $userId = null, ?string $action = null): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $conditions = [];

        $sql = "SELECT * FROM {$this->table}";

        if ($userId !== null) {
            $conditions[] = "user_id = :user_id";
            $params['user_id'] = $userId;
        }

        if ($action !== null) {
            $conditions[] = "action = :action";
            $params['action'] = $action;
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
     * Count the total number of audit log entries.
     *
     * @param int|null    $userId Optional user ID filter.
     * @param string|null $action Optional action filter.
     * @return int The total count.
     */
    public function count(?int $userId = null, ?string $action = null): int
    {
        $params = [];
        $conditions = [];

        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if ($userId !== null) {
            $conditions[] = "user_id = :user_id";
            $params['user_id'] = $userId;
        }

        if ($action !== null) {
            $conditions[] = "action = :action";
            $params['action'] = $action;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        return $this->db->count($sql, $params);
    }

    /**
     * Log an audit event (static convenience method).
     *
     * Creates a new audit log entry with the provided parameters.
     * This is a fire-and-forget method — exceptions are silently caught.
     *
     * @param int|null    $userId     The acting user's ID, or null for system actions.
     * @param int|null    $deviceId   The associated device ID, or null.
     * @param string      $action     The action performed (e.g., 'user.login', 'license.activate').
     * @param string|null $targetType The type of target entity (e.g., 'license', 'device').
     * @param int|null    $targetId   The target entity's ID.
     * @param string|null $ip         The client's IP address.
     * @param string|null $userAgent  The client's User-Agent string.
     * @param array|null  $metadata   Optional additional metadata.
     * @return void
     */
    public static function log(
        ?int $userId,
        ?int $deviceId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): void {
        try {
            $instance = new self();
            $instance->create([
                'user_id'     => $userId,
                'device_id'   => $deviceId,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
                'metadata'    => $metadata !== null ? json_encode($metadata) : null,
            ]);
        } catch (\Throwable $e) {
            // Silently fail — audit logging should never break the request
            error_log('AuditLog::log failed: ' . $e->getMessage());
        }
    }
}
