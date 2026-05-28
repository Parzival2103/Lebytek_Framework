<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto para scopes de listado (CrudListScopeInterface).
 * Las condiciones se acumulan estructuradas; el motor arma SQL con
 * quoteIdentifier + params. Solo se aceptan operadores en whitelist.
 */
final class CrudListContext extends CrudContext
{
    private const ALLOWED_OPS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN'];

    /** @var list<array{column: string, op: string, value: mixed}> */
    private array $conditions = [];

    /** @param array<string, mixed> $query */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $query
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    public function addCondition(string $column, string $op, mixed $value): void
    {
        $normalizedOp = strtoupper(trim($op));
        if (!in_array($normalizedOp, self::ALLOWED_OPS, true)) {
            throw new \InvalidArgumentException("Operador de condición no permitido: {$op}");
        }
        $this->conditions[] = ['column' => $column, 'op' => $normalizedOp, 'value' => $value];
    }

    /** @return list<array{column: string, op: string, value: mixed}> */
    public function conditions(): array
    {
        return $this->conditions;
    }
}
