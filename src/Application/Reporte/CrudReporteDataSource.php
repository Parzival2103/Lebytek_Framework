<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Application\Services\CrudRelationService;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\AccesoException;
use Lebytek\Framework\Domain\Reporte\ReporteDataSourceInterface;
use Lebytek\Framework\Domain\Reporte\ReporteRecordSourceInterface;

/**
 * Adaptador de lectura de datos para reportes sobre CrudDataService.
 */
final class CrudReporteDataSource implements ReporteDataSourceInterface, ReporteRecordSourceInterface
{
    public function __construct(
        private readonly CrudDataService $crudDataService,
        private readonly CrudRelationService $crudRelationService,
    ) {}

    /**
     * Exige `{permission_prefix}.ver` del recurso además de los permisos del
     * módulo Reportes: generar un reporte no habilita leer un recurso vedado.
     *
     * @internal Público para pruebas del paquete; no es contrato de consumidor.
     */
    public static function assertCanViewResource(CrudResourceDefinition $definition, ?callable $can): void
    {
        $slug = $definition->permissionFor('ver');
        if ($can === null || !$can($slug)) {
            throw new AccesoException("No tienes permiso para realizar esta acción: {$slug}");
        }
    }

    public function rows(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId,
        ?callable $can,
        array $filters
    ): array {
        self::assertCanViewResource($definition, $can);
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
        self::assertCanViewResource($definition, $can);
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
