<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Context;

/**
 * Contexto para enriquecer filas del listado CRUD tras la consulta a BD
 * y antes del formateo de la tabla (hook afterListRows).
 */
final class CrudListRowsContext extends CrudContext
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $query
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private array $rows,
        private readonly array $query = [],
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @param list<array<string, mixed>> $rows */
    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }
}
