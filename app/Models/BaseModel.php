<?php
/**
 * BetVibe - Base Model
 * Provides common CRUD operations for all models
 */

namespace App\Models;

use App\Core\DB;
use PDO;

abstract class BaseModel
{
    protected string $table;
    protected array $fillable = [];
    protected ?PDO $db = null;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * Find a record by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find records by conditions
     * @param array $conditions Associative array of conditions ['column' => 'value']
     * @return array
     */
    public function where(array $conditions): array
    {
        if (empty($conditions)) {
            return [];
        }

        $whereClause = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClause[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Create a new record
     * @param array $data Associative array of data to insert
     * @return int|false The inserted ID or false on failure
     */
    public function create(array $data): int|false
    {
        // Filter only fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        // Auto-set created_at if not provided and column exists
        if (!isset($filteredData['created_at'])) {
            $filteredData['created_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($filteredData);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($filteredData);

        return $result ? (int) $this->db->lastInsertId() : false;
    }

    /**
     * Update a record by ID
     * @param int $id The record ID
     * @param array $data Associative array of data to update
     * @return bool True on success, false on failure
     */
    public function update(int $id, array $data): bool
    {
        // Filter only fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        // Auto-set updated_at if not provided and column exists
        if (!isset($filteredData['updated_at'])) {
            $filteredData['updated_at'] = date('Y-m-d H:i:s');
        }

        $setClause = [];
        foreach (array_keys($filteredData) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }

        $sql = sprintf(
            "UPDATE {$this->table} SET %s WHERE id = :id",
            implode(', ', $setClause)
        );

        $filteredData['id'] = $id;
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($filteredData);
    }

    /**
     * Delete a record by ID
     * @param int $id The record ID
     * @return bool True on success, false on failure
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get all records
     * @return array
     */
    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Get the PDO connection
     */
    protected function getConnection(): PDO
    {
        return $this->db;
    }
}
