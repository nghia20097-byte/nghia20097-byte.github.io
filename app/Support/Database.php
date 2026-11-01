<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO database wrapper.
 *
 * Provides a clean interface around PDO with prepared statements,
 * transaction support, and common query shortcuts.
 *
 * @package App\Support
 */
class Database
{
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * The PDO connection instance.
     *
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Create a new Database instance.
     *
     * @param array{host: string, port?: int, dbname: string, username: string, password: string, charset?: string} $config
     * @throws RuntimeException If the database connection fails.
     */
    private function __construct(array $config)
    {
        $host    = $config['host'] ?? '127.0.0.1';
        $port    = $config['port'] ?? 3306;
        $dbname  = $config['dbname'] ?? '';
        $user    = $config['username'] ?? 'root';
        $pass    = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone(): void
    {
    }

    /**
     * Get or create the singleton instance.
     *
     * @param array $config Database configuration array.
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException('Database configuration is required for initial connection.');
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing).
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the underlying PDO connection.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a SQL query with optional bound parameters.
     *
     * @param string $sql    The SQL statement to execute.
     * @param array  $params Parameters to bind to the statement.
     * @return PDOStatement
     * @throws PDOException If the query fails.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row from a query.
     *
     * @param string $sql    The SQL statement.
     * @param array  $params Parameters to bind.
     * @return array|null The row as an associative array, or null if not found.
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all rows from a query.
     *
     * @param string $sql    The SQL statement.
     * @param array  $params Parameters to bind.
     * @return array An array of associative arrays.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table The table name.
     * @param array  $data  Column => value pairs to insert.
     * @return int The last inserted ID.
     * @throws PDOException If the insert fails.
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn(string $col): string => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update rows in a table.
     *
     * @param string $table       The table name.
     * @param array  $data        Column => value pairs to update.
     * @param string $where       The WHERE clause (without the WHERE keyword).
     * @param array  $whereParams Parameters for the WHERE clause.
     * @return int The number of affected rows.
     * @throws PDOException If the update fails.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn(string $col): string => "`{$col}` = ?", array_keys($data));
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows from a table.
     *
     * @param string $table       The table name.
     * @param string $where       The WHERE clause (without the WHERE keyword).
     * @param array  $whereParams Parameters for the WHERE clause.
     * @return int The number of deleted rows.
     * @throws PDOException If the delete fails.
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Count rows in a table matching a condition.
     *
     * @param string $table  The table name.
     * @param string $where  The WHERE clause (defaults to '1=1' for all rows).
     * @param array  $params Parameters for the WHERE clause.
     * @return int The row count.
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
