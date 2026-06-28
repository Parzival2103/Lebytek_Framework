<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CalendarEventMapper;
use Lebytek\Framework\Application\UseCases\Calendar\ListarEventosCalendarioUseCase;
use Lebytek\Framework\Domain\Calendar\DateRange;
use Lebytek\Framework\Domain\Interfaces\CalendarEventSourceInterface;

test('UseCase devuelve eventos normalizados a partir de filas scoped', function (): void {
    $fakeSource = new class implements CalendarEventSourceInterface {
        /** @var array<string,mixed> */
        public array $lastArgs = [];

        public function eventosCalendario(string $resource, string $dateColumn, DateRange $range, ?int $userId, array $filters = []): array
        {
            $this->lastArgs = [
                'resource' => $resource,
                'dateColumn' => $dateColumn,
                'userId' => $userId,
                'from' => $range->from()->format('Y-m-d'),
                'to' => $range->to()->format('Y-m-d'),
                'filters' => $filters,
            ];
            return [[
                'id' => 1, 'cliente' => 'López', 'servicio' => 'Corte', 'estado' => 'confirmada',
                'fecha_inicio' => '2026-06-09 10:00:00', 'fecha_fin' => '2026-06-09 11:00:00',
            ]];
        }
    };

    $useCase = new ListarEventosCalendarioUseCase(
        new CalendarConfigLoader(new CalendarConfigValidator()),
        $fakeSource,
        new CalendarEventMapper()
    );

    $events = $useCase->execute('demo_citas', DateRange::forMonth(2026, 6), 7, ['estado' => 'confirmada']);

    assert_same(1, count($events), 'un evento');
    assert_same('López — Corte', $events[0]['title'], 'título mapeado');
    assert_same('success', $events[0]['color'], 'color por estado');
    assert_same('/admin/crud/demo_citas/1', $events[0]['url'], 'url a show');

    // El UseCase resuelve resource + columna de inicio desde la definición y propaga args.
    assert_same('demo_citas', $fakeSource->lastArgs['resource'], 'resource desde la definición');
    assert_same('fecha_inicio', $fakeSource->lastArgs['dateColumn'], 'columna de inicio (mapping.start)');
    assert_same(7, $fakeSource->lastArgs['userId'], 'userId propagado');
    assert_same('2026-06-01', $fakeSource->lastArgs['from'], 'rango from');
    assert_same('2026-06-30', $fakeSource->lastArgs['to'], 'rango to');
    assert_same(['estado' => 'confirmada'], $fakeSource->lastArgs['filters'], 'filtros propagados');
});
