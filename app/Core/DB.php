<?php
/**
 * BetVibe - Database Class
 * PDO-based database class with singleton pattern
 */

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class DB
{
    private static ?DB $instance = null;
    private ?PDO $connection = null;
    private array $config = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_NAME'] ?? 'betvibe',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]
        ];

        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

            // Set timezone
            $timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
            $this->connection->exec("SET time_zone = '+00:00'");
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Execute a query with parameters
     *
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);

            foreach ($params as $key => $value) {
                $paramType = $this->getParamType($value);

                if (is_int($key)) {
                    // Positional parameter (1-indexed)
                    $stmt->bindValue($key + 1, $value, $paramType);
                } else {
                    // Named parameter
                    $stmt->bindValue($key, $value, $paramType);
                }
            }

            $stmt->execute();
            return $stmt;

        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql,
                0,
                $e
            );
        }
    }

    /**
     * Get first row from query result
     *
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return array|null First row or null if no results
     */
    public function first(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all rows from query result
     *
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return array All rows
     */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a transaction
     *
     * @param callable $callback Function to execute within transaction
     * @return mixed Result of the callback
     * @throws \Exception If transaction fails
     */
    public function transaction(callable $callback)
    {
        try {
            $this->connection->beginTransaction();

            $result = $callback($this);

            $this->connection->commit();

            return $result;

        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insert a record and return last insert ID
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|string Last insert ID
     */
    public function insert(string $table, array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return $this->connection->lastInsertId();
    }

    /**
     * Update records
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = $this->quoteIdentifier($column) . ' = :' . $column;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            $where
        );

        $params = array_merge($data, $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     *
     * @param string $table Table name
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $params Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            $where
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Check if a record exists
     *
     * @param string $table Table name
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $params Parameters for WHERE clause
     * @return bool True if record exists
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s LIMIT 1',
            $this->quoteIdentifier($table),
            $where
        );

        return $this->first($sql, $params) !== null;
    }

    /**
     * Count records
     *
     * @param string $table Table name
     * @param string $where WHERE clause (without WHERE keyword), empty for all records
     * @param array $params Parameters for WHERE clause
     * @return int Number of records
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            $where
        );

        $result = $this->first($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get PDO parameter type based on value
     */
    private function getParamType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Quote identifier (table/column name)
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Execute raw SQL (use with caution)
     *
     * @param string $sql Raw SQL query
     * @return bool|PDOStatement
     */
    public function exec(string $sql)
    {
        try {
            return $this->connection->exec($sql);
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Raw SQL execution failed: ' . $e->getMessage() . ' | SQL: ' . $sql,
                0,
                $e
            );
        }
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string|false
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Reconnect to database
     */
    public function reconnect(): void
    {
        $this->connection = null;
        $this->connect();
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
