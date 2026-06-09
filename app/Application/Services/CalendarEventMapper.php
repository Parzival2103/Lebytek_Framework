<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Calendar\CalendarEvent;
use App\Domain\Entities\CalendarDefinition;

final class CalendarEventMapper
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<CalendarEvent>
     */
    public function map(array $rows, CalendarDefinition $def, string $resource): array
    {
        $events = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $start = (string)($row[$def->mappingStart()] ?? '');
            if ($start === '') {
                continue;
            }
            $endCol = $def->mappingEnd();
            $end = ($endCol !== null && isset($row[$endCol]) && $row[$endCol] !== null && $row[$endCol] !== '')
                ? (string)$row[$endCol] : null;

            $events[] = new CalendarEvent(
                id: $id,
                title: $this->title($def, $row, $id),
                start: $start,
                end: $end,
                allDay: $this->allDay($def, $start),
                color: $this->color($def, $row),
                url: '/admin/crud/' . $resource . '/' . $id,
            );
        }
        return $events;
    }

    /** @param array<string,mixed> $row */
    private function title(CalendarDefinition $def, array $row, int $id): string
    {
        $tpl = $def->mappingTitle();
        if ($tpl === '') {
            return '#' . $id;
        }
        return (string) preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($row): string {
            return (string)($row[$m[1]] ?? '');
        }, $tpl);
    }

    private function allDay(CalendarDefinition $def, string $start): bool
    {
        $explicit = $def->mappingAllDay();
        if ($explicit !== null) {
            return $explicit;
        }
        // Sin hora (solo fecha) => all-day.
        return !str_contains($start, ':');
    }

    /** @param array<string,mixed> $row */
    private function color(CalendarDefinition $def, array $row): string
    {
        return match ($def->colorBy()) {
            'estado' => (string)($def->colorMap()[(string)($row['estado'] ?? '')] ?? 'secondary'),
            'field'  => (string)($row[$def->colorField()] ?? 'secondary'),
            default  => $def->colorFixed(),
        };
    }
}
