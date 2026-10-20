<?php
/**
 * Model.php
 * -------------------------------------------------------
 * Abstract base class for all data models (Product, Box,
 * Sale, User, etc.)
 *
 * Demonstrates: Abstraction, Encapsulation, Inheritance.
 * Every child class MUST implement getTable() and getFields(),
 * forcing a consistent contract across all models.
 * -------------------------------------------------------
 */

abstract class Model
{
    /** @var PDO Protected so child classes can use it directly */
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Abstract method: each child model must define its own
     * database table name.
     */
    abstract protected function getTable(): string;

    /**
     * Generic "find by ID" usable by ANY child class without
     * rewriting the same SQL each time.
     */
    public function find(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->getTable()} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
// SQL injection attempts and database access
    public function all(string $orderBy = 'id DESC'): array
    {
    // Whitelist safe sort patterns to prevent SQL injection if ever misused
    $safePattern = '/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?)*$/i';
    if (!preg_match($safePattern, $orderBy)) {
        $orderBy = 'id DESC';
    }
    $stmt = $this->db->query("SELECT * FROM {$this->getTable()} ORDER BY {$orderBy}");
    return $stmt->fetchAll();
    }

    /**
     * Generic delete by ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->getTable()} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Returns the last inserted ID — useful after INSERT calls.
     */
    public function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }
}
