<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Services\CrudDataService;
use App\Application\Services\CrudRelationService;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Reporte\ReporteDataSourceInterface;
use App\Domain\Reporte\ReporteRecordSourceInterface;

/**
 * Adaptador de lectura de datos para reportes sobre CrudDataService.
 */
final class CrudReporteDataSource implements ReporteDataSourceInterface, ReporteRecordSourceInterface
{
    public function __construct(
        private readonly CrudDataService $crudDataService,
        private readonly CrudRelationService $crudRelationService,
    ) {}

    public function rows(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId,
        ?callable $can,
        array $filters
    ): array {
        return $this->crudDataService->eventsInRange(
            $definition,
            $dateColumn,
            $from,
            $to,
            $userId,
            $can,
            $filters
        );
    }

    public function findRecord(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId,
        ?callable $can,
        array $relationNames
    ): ?array {
        $record = $this->crudDataService->findInScope($definition, $id, $userId, $can);
        if ($record === null) {
            return null;
        }

        $relations = [];
        foreach ($relationNames as $name) {
            $name = (string) $name;
            $relation = $definition->relation($name);
            if ($relation === null) {
                continue;
            }
            if ($relation->isBelongsTo()) {
                $options = $this->crudRelationService->optionsFor($relation);
                $fkValue = (string) ($record[$relation->foreignKey()] ?? '');
                $relations[$name] = $options[$fkValue] ?? null;
            } elseif ($relation->isHasMany()) {
                $relations[$name] = $this->crudRelationService->childrenFor($relation, $id);
            }
        }

        return ['record' => $record, 'relations' => $relations];
    }
}
