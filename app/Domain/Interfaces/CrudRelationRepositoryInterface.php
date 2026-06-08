<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

/**
 * Contrato de lectura para relaciones del CRUD Engine. Implementado por
 * GenericCrudRepository. Toda columna/filtro pasa por whitelist en la impl.
 */
interface CrudRelationRepositoryInterface
{
    /**
     * Opciones value=>label para selects belongsTo. `$filter` es estructurado
     * ({columna: valor}); nunca SQL crudo.
     *
     * @param array<string, mixed> $filter
     * @return array<string, string>
     */
    public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array;

    /**
     * Filas hijas para tabs hasMany (read-only).
     *
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array;
}
