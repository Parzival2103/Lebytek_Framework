<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarEventMapper;
use Lebytek\Framework\Domain\Entities\CalendarDefinition;

function map_def(array $overrides = []): CalendarDefinition
{
    return CalendarDefinition::fromArray(array_replace_recursive([
        'calendar' => ['key' => 'citas', 'title' => 'A', 'resource' => 'demo_citas'],
        'mapping' => ['start' => 'fecha_inicio', 'end' => 'fecha_fin', 'title' => '{cliente} — {servicio}',
                      'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning', 'confirmada' => 'success']]],
        'views' => ['default' => 'month', 'enabled' => ['month']],
    ], $overrides));
}

test('CalendarEventMapper mapea fila con plantilla, color por estado y url', function (): void {
    $rows = [[
        'id' => 5, 'cliente' => 'López', 'servicio' => 'Corte', 'estado' => 'confirmada',
        'fecha_inicio' => '2026-06-09 10:00:00', 'fecha_fin' => '2026-06-09 11:00:00',
    ]];
    $events = (new CalendarEventMapper())->map($rows, map_def(), 'demo_citas');
    assert_same(1, count($events), 'un evento');
    $a = $events[0]->toArray();
    assert_same('López — Corte', $a['title'], 'título por plantilla');
    assert_same('success', $a['color'], 'color por estado');
    assert_same('/admin/crud/demo_citas/5', $a['url'], 'url a show');
    assert_same(false, $a['allDay'], 'datetime => no all-day');
});

test('CalendarEventMapper marca all-day cuando la fecha no tiene hora', function (): void {
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'estado' => 'pendiente',
              'fecha_inicio' => '2026-06-09', 'fecha_fin' => null]];
    $events = (new CalendarEventMapper())->map($rows, map_def(), 'demo_citas');
    assert_same(true, $events[0]->toArray()['allDay'], 'fecha sin hora => all-day');
    assert_same(null, $events[0]->toArray()['end'], 'end nulo');
});

test('CalendarEventMapper color fixed usa el valor configurado', function (): void {
    $def = map_def(['mapping' => ['color' => ['by' => 'fixed', 'value' => 'info', 'map' => []]]]);
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'estado' => 'pendiente',
              'fecha_inicio' => '2026-06-09 09:00:00', 'fecha_fin' => null]];
    assert_same('info', (new CalendarEventMapper())->map($rows, $def, 'demo_citas')[0]->toArray()['color'], 'color fijo');
});

test('CalendarEventMapper color por field arbitrario lee la columna declarada', function (): void {
    $def = map_def(['mapping' => ['color' => ['by' => 'field', 'field' => 'tono', 'map' => []]]]);
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'tono' => 'danger',
              'fecha_inicio' => '2026-06-09 09:00:00', 'fecha_fin' => null]];
    assert_same('danger', (new CalendarEventMapper())->map($rows, $def, 'demo_citas')[0]->toArray()['color'], 'color desde field');
});

test('CalendarEventMapper omite filas sin fecha de inicio', function (): void {
    $rows = [['id' => 1, 'cliente' => 'X', 'servicio' => 'Y', 'estado' => 'pendiente',
              'fecha_inicio' => '', 'fecha_fin' => null]];
    assert_same(0, count((new CalendarEventMapper())->map($rows, map_def(), 'demo_citas')), 'sin inicio => sin evento');
});
