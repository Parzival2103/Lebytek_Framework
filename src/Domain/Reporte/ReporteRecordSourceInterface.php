<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Reporte;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

/**
 * Frontera de lectura de UN registro con sus relaciones declaradas, respetando el
 * mismo row-level scope que el listado CRUD.
 */
interface ReporteRecordSourceInterface
{
    /**
     * @param list<string> $relationNames relaciones CRUD a cargar (belongsTo|hasMany)
     * @return array{record: array<string,mixed>, relations: array<string,mixed>}|null
     */
    public function findRecord(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId,
        ?callable $can,
        array $relationNames
    ): ?array;
}
