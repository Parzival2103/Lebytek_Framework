<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\CrudConstraintRepositoryInterface;
use App\Domain\Interfaces\CrudRelationRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class GenericCrudRepository extends BaseRepository implements CrudConstraintRepositoryInterface, CrudRelationRepositoryInterface
{
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public function tableExists(string $table): bool
    {
        $row = $this->queryOne(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function permissionExists(string $slug): bool
    {
        $row = $this->queryOne(
            'SELECT COUNT(*) AS total FROM auth_permisos WHERE slug = ?',
            [$slug]
        );
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function getTableColumns(string $table): array
    {
        $rows = $this->query(
            'SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ordinal_position ASC',
            [$table]
        );

        return array_map(static fn(array $row): string => (string) $row['column_name'], $rows);
    }

    public function selectPaginated(
        string $table,
        array $selectColumns,
        array $whereSqlParts,
        array $params,
        string $orderBy,
        string $orderDirection,
        int $limit,
        int $offset
    ): array {
        $safeTable = $this->quoteIdentifier($table);
        $columns   = implode(', ', array_map([$this, 'quoteIdentifier'], $selectColumns));

        $whereSql = empty($whereSqlParts) ? '' : ' WHERE ' . implode(' AND ', $whereSqlParts);
        $sql      = "SELECT {$columns} FROM {$safeTable}{$whereSql} ORDER BY " . $this->quoteIdentifier($orderBy) . ' ' . $orderDirection . ' LIMIT ? OFFSET ?';

        $rows = $this->query($sql, array_merge($params, [$limit, $offset]));

        $countSql = "SELECT COUNT(*) AS total FROM {$safeTable}{$whereSql}";
        $countRow = $this->queryOne($countSql, $params);

        return [
            'rows'  => $rows,
            'total' => (int) ($countRow['total'] ?? 0),
        ];
    }

    public function countFiltered(string $table, array $whereSqlParts, array $params): int
    {
        $safeTable = $this->quoteIdentifier($table);
        $whereSql = empty($whereSqlParts) ? '' : ' WHERE ' . implode(' AND ', $whereSqlParts);
        $countRow = $this->queryOne("SELECT COUNT(*) AS total FROM {$safeTable}{$whereSql}", $params);

        return (int) ($countRow['total'] ?? 0);
    }

    /**
     * @param list<array{type:string,column:string}> $summaries
     * @return array{rows: list<array<string,mixed>>, total:int}
     */
    public function selectGroupedAggregates(
        string $table,
        string $groupBy,
        array $summaries,
        array $whereSqlParts,
        array $params,
        string $orderDirection,
        int $limit,
        int $offset
    ): array {
        $safeTable = $this->quoteIdentifier($table);
        $groupCol  = $this->quoteIdentifier($groupBy);

        $selectParts = [$groupCol . ' AS ' . $this->quoteIdentifier($groupBy)];
        foreach ($summaries as $summary) {
            $type = (string) ($summary['type'] ?? '');
            $column = (string) ($summary['column'] ?? '');
            if ($type === 'sum') {
                $alias = 'crud_sum_' . $column;
                $selectParts[] = 'SUM(' . $this->quoteIdentifier($column) . ') AS ' . $this->quoteIdentifier($alias);
            } elseif ($type === 'count') {
                $alias = 'crud_cnt_' . $column;
                $selectParts[] = 'COUNT(*) AS ' . $this->quoteIdentifier($alias);
            }
        }

        $whereSql = empty($whereSqlParts) ? '' : ' WHERE ' . implode(' AND ', $whereSqlParts);

        $innerSql = 'SELECT ' . implode(', ', $selectParts) . " FROM {$safeTable}{$whereSql} GROUP BY {$groupCol}";

        $countSql = "SELECT COUNT(*) AS total FROM ({$innerSql}) AS crud_groups";
        $countRow = $this->queryOne($countSql, $params);
        $total = (int) ($countRow['total'] ?? 0);

        $dir = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM ({$innerSql}) AS crud_groups ORDER BY " . $this->quoteIdentifier($groupBy) . " {$dir} LIMIT ? OFFSET ?";
        $rows = $this->query($sql, array_merge($params, [$limit, $offset]));

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * @param list<array{type:string,column:string}> $summaries
     * @return array<string, mixed>
     */
    public function selectGlobalAggregates(
        string $table,
        array $summaries,
        array $whereSqlParts,
        array $params
    ): array {
        $safeTable = $this->quoteIdentifier($table);
        $selectParts = [];
        foreach ($summaries as $summary) {
            $type = (string) ($summary['type'] ?? '');
            $column = (string) ($summary['column'] ?? '');
            if ($type === 'sum') {
                $alias = 'crud_sum_' . $column;
                $selectParts[] = 'SUM(' . $this->quoteIdentifier($column) . ') AS ' . $this->quoteIdentifier($alias);
            } elseif ($type === 'count') {
                $alias = 'crud_cnt_' . $column;
                $selectParts[] = 'COUNT(*) AS ' . $this->quoteIdentifier($alias);
            }
        }

        if ($selectParts === []) {
            return [];
        }

        $whereSql = empty($whereSqlParts) ? '' : ' WHERE ' . implode(' AND ', $whereSqlParts);
        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM {$safeTable}{$whereSql}";

        $row = $this->queryOne($sql, $params);
        return is_array($row) ? $row : [];
    }

    public function findById(string $table, string $primaryKey, int $id): ?array
    {
        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = ? LIMIT 1';
        return $this->queryOne($sql, [$id]);
    }

    public function insertRecord(string $table, array $payload): int
    {
        $columns = array_keys($payload);
        $safeColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', $safeColumns) . ') VALUES (' . $placeholders . ')';

        return $this->insert($sql, array_values($payload));
    }

    public function updateRecord(string $table, string $primaryKey, int $id, array $payload): int
    {
        $sets = [];
        $params = [];
        foreach ($payload as $column => $value) {
            $sets[] = $this->quoteIdentifier((string) $column) . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE ' . $this->quoteIdentifier($table)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = ?';

        return $this->execute($sql, $params);
    }

    public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeCol   = $this->quoteIdentifier($column);
        $safePk    = $this->quoteIdentifier($primaryKey);

        $sql = "SELECT COUNT(*) AS total FROM {$safeTable} WHERE {$safeCol} = ? AND `deleted` = 0";
        $params = [$value];
        if ($exceptId !== null) {
            $sql .= " AND {$safePk} <> ?";
            $params[] = $exceptId;
        }

        $row = $this->queryOne($sql, $params);

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function existsForReference(string $table, string $column, mixed $value): bool
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeCol   = $this->quoteIdentifier($column);

        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM {$safeTable} WHERE {$safeCol} = ? LIMIT 1",
            [$value]
        );

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeValue = $this->quoteIdentifier($valueColumn);
        $safeLabel = $this->quoteIdentifier($labelColumn);

        $where = ['`deleted` = 0'];
        $params = [];
        foreach ($filter as $col => $val) {
            $where[] = $this->quoteIdentifier((string) $col) . ' = ?';
            $params[] = $val;
        }

        $orderCol = $orderBy !== '' ? $this->quoteIdentifier($orderBy) : $safeLabel;
        $sql = "SELECT {$safeValue} AS opt_value, {$safeLabel} AS opt_label FROM {$safeTable}"
            . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY {$orderCol} ASC LIMIT 1000";

        $rows = $this->query($sql, $params);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['opt_value'] ?? '')] = (string) ($row['opt_label'] ?? '');
        }

        return $out;
    }

    public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array
    {
        $safeTable = $this->quoteIdentifier($table);
        $safeFk    = $this->quoteIdentifier($foreignKey);

        $cols = $columns === [] ? '*' : implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $orderCol = $orderBy !== '' ? $this->quoteIdentifier($orderBy) : $safeFk;
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $limit = $limit > 0 && $limit <= 500 ? $limit : 50;

        $sql = "SELECT {$cols} FROM {$safeTable} WHERE {$safeFk} = ? AND `deleted` = 0 ORDER BY {$orderCol} {$dir} LIMIT ?";

        return $this->query($sql, [$parentId, $limit]);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException('Identificador SQL inválido: ' . $identifier);
        }
        return '`' . $identifier . '`';
    }
}
