<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Interfaces\CrudRelationRepositoryInterface;

/**
 * Resuelve datos de relaciones: opciones de selects belongsTo y filas hijas
 * hasMany (read-only). No decide reglas de negocio; solo lee del repositorio.
 */
final class CrudRelationService
{
    public function __construct(private readonly CrudRelationRepositoryInterface $repository) {}

    /** @return array<string, string> */
    public function optionsFor(CrudRelationDefinition $relation): array
    {
        if (!$relation->isBelongsTo()) {
            return [];
        }

        return $this->repository->distinctOptions(
            $relation->table(),
            $relation->valueColumn(),
            $relation->labelColumn(),
            $relation->filter(),
            $relation->orderBy()
        );
    }

    /** @return list<array<string, mixed>> */
    public function childrenFor(CrudRelationDefinition $relation, int $parentId): array
    {
        if (!$relation->isHasMany()) {
            return [];
        }

        $columnNames = [];
        foreach ($relation->columns() as $col) {
            $name = (string) ($col['name'] ?? '');
            if ($name !== '') {
                $columnNames[] = $name;
            }
        }

        return $this->repository->childrenBy(
            $relation->table(),
            $relation->foreignKey(),
            $parentId,
            $columnNames,
            $relation->orderBy(),
            $relation->direction(),
            $relation->limit()
        );
    }
}
