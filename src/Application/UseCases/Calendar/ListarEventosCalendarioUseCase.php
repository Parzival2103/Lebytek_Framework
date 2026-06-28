<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Calendar;

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarEventMapper;
use Lebytek\Framework\Domain\Calendar\DateRange;
use Lebytek\Framework\Domain\Interfaces\CalendarEventSourceInterface;

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
