<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Domain\Calendar\DateRange;

/**
 * Fuente de filas para el feed de eventos de un calendario. La implementación
 * concreta aplica permisos y scope (row-level) del recurso CRUD subyacente.
 */
interface CalendarEventSourceInterface
{
    /**
     * Devuelve las filas del recurso dentro del rango, ya filtradas por scope.
     *
     * @param array<string,mixed> $filters columna => valor (igualdad)
     * @return list<array<string,mixed>>
     */
    public function eventosCalendario(
        string $resource,
        string $dateColumn,
        DateRange $range,
        ?int $userId,
        array $filters = []
    ): array;
}
