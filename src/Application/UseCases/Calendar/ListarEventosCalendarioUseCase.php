<?php
declare(strict_types=1);

namespace App\Application\UseCases\Calendar;

use App\Application\Services\CalendarConfigLoader;
use App\Application\Services\CalendarEventMapper;
use App\Domain\Calendar\DateRange;
use App\Domain\Interfaces\CalendarEventSourceInterface;

final class ListarEventosCalendarioUseCase
{
    public function __construct(
        private readonly CalendarConfigLoader $calendarLoader,
        private readonly CalendarEventSourceInterface $eventSource,
        private readonly CalendarEventMapper $mapper,
    ) {}

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>> eventos en forma JSON
     */
    public function execute(string $calendarKey, DateRange $range, ?int $userId, array $filters = []): array
    {
        $def = $this->calendarLoader->load($calendarKey);
        $rows = $this->eventSource->eventosCalendario(
            $def->resource(),
            $def->mappingStart(),
            $range,
            $userId,
            $filters
        );
        $events = $this->mapper->map($rows, $def, $def->resource());
        return array_map(static fn($e): array => $e->toArray(), $events);
    }
}
