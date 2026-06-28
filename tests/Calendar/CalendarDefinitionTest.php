<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CalendarDefinition;

function cd_sample(): array
{
    return [
        'calendar' => ['key' => 'citas', 'title' => 'Agenda de Citas', 'resource' => 'demo_citas', 'icon' => 'bi-calendar-event'],
        'mapping' => [
            'start' => 'fecha_inicio', 'end' => 'fecha_fin', 'all_day' => false,
            'title' => '{cliente} — {servicio}',
            'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning', 'confirmada' => 'success']],
        ],
        'views' => ['default' => 'month', 'enabled' => ['month', 'week', 'day', 'table']],
        'interaction' => ['create_on_slot' => true, 'open_detail' => true, 'edit_from_event' => true],
        'filters' => [['field' => 'estado', 'label' => 'Estado']],
        'dashboard_widget' => true,
    ];
}

test('CalendarDefinition::fromArray expone accesores', function (): void {
    $d = CalendarDefinition::fromArray(cd_sample());
    assert_same('citas', $d->key(), 'key');
    assert_same('demo_citas', $d->resource(), 'resource');
    assert_same('fecha_inicio', $d->mappingStart(), 'start');
    assert_same('fecha_fin', $d->mappingEnd(), 'end');
    assert_same(false, $d->mappingAllDay(), 'all_day');
    assert_same('{cliente} — {servicio}', $d->mappingTitle(), 'title');
    assert_same('estado', $d->colorBy(), 'color.by');
    assert_same('success', $d->colorMap()['confirmada'] ?? null, 'color.map');
    assert_same('month', $d->viewsDefault(), 'default view');
    assert_same(['month', 'week', 'day', 'table'], $d->viewsEnabled(), 'enabled views');
    assert_true($d->dashboardWidget(), 'dashboard_widget');
});

test('CalendarDefinition aplica defaults cuando faltan opcionales', function (): void {
    $d = CalendarDefinition::fromArray([
        'calendar' => ['key' => 'k', 'title' => 'T', 'resource' => 'r'],
        'mapping' => ['start' => 'fecha'],
        'views' => ['default' => 'month', 'enabled' => ['month']],
    ]);
    assert_same(null, $d->mappingEnd(), 'end por defecto null');
    assert_same('fixed', $d->colorBy(), 'color.by por defecto fixed');
    assert_same(false, $d->dashboardWidget(), 'dashboard_widget por defecto false');
    assert_same([], $d->filters(), 'filters por defecto vacío');
});
