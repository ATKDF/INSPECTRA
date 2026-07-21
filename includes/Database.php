<?php
/**
 * INSPECTRA - Database Abstraction Layer
 * Uses PDO with prepared statements for all queries (prevents SQL injection)
 */

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    /** Execute query and return PDOStatement */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch single row */
    public function row(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /** Fetch all rows */
    public function all(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Fetch single column value */
    public function scalar(string $sql, array $params = []): mixed {
        return $this->query($sql, $params)->fetchColumn();
    }

    /** Insert and return last insert ID */
    public function insert(string $table, array $data): int {
        $cols   = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $sql    = "INSERT INTO `$table` ($cols) VALUES ($places)";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** Update rows and return affected count */
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set  = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $sql  = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /** Begin transaction */
    public function beginTransaction(): void {
        $this->pdo->beginTransaction();
    }

    /** Commit transaction */
    public function commit(): void {
        $this->pdo->commit();
    }

    /** Rollback transaction */
    public function rollback(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** Get row count of last query */
    public function count(string $sql, array $params = []): int {
        return (int) $this->scalar($sql, $params);
    }

    /** Check if a record exists */
    public function exists(string $table, string $where, array $params = []): bool {
        $count = $this->scalar("SELECT COUNT(*) FROM `$table` WHERE $where LIMIT 1", $params);
        return $count > 0;
    }

    /** Get setting value */
    public function getSetting(string $key, mixed $default = null): mixed {
        $val = $this->scalar("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $val !== false ? $val : $default;
    }

    /** Set setting value */
    public function setSetting(string $key, mixed $value, ?int $updatedBy = null): void {
        $this->query(
            "INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)",
            [$key, $value, $updatedBy]
        );
    }
}
