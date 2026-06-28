<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Reporte;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

/**
 * Frontera de lectura de datos para reportes. La implementación real adapta
 * CrudDataService (scope + filtros del CRUD Engine); los tests inyectan un doble.
 */
interface ReporteDataSourceInterface
{
    /**
     * Filas del recurso dentro de [from, to] sobre $dateColumn, respetando el scope.
     *
     * @param array<string,mixed> $filters columna => valor (igualdad)
     * @return list<array<string,mixed>>
     */
    public function rows(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId,
        ?callable $can,
        array $filters
    ): array;
}
