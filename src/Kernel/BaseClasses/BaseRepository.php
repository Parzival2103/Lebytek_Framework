<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\BaseClasses;

use PDO;
use Lebytek\Framework\Kernel\Database\Connection;

/*
|--------------------------------------------------------------------------
| BaseRepository — Repositorio base con helpers PDO
|--------------------------------------------------------------------------
| Proporciona métodos comunes de consulta para los repositorios concretos.
| Toda interacción con la BD usa prepared statements.
*/

abstract class BaseRepository
{
    protected PDO $db;
    protected string $table = '';

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return $rows[0] ?? null;
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    protected function count(string $whereClause = '', array $params = []): int
    {
        $sql  = "SELECT COUNT(*) FROM {$this->table}";
        $sql .= $whereClause ? " WHERE {$whereClause}" : '';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    protected function findRowById(int $id): ?array
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    protected function findRows(string $orderBy = 'id DESC', int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    protected function softDelete(int $id): int
    {
        return $this->execute(
            "UPDATE {$this->table} SET activo = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    // ── Transacciones ─────────────────────────────────────────────────────────

    protected function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    protected function commit(): void
    {
        $this->db->commit();
    }

    protected function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
