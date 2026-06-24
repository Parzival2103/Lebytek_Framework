<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteAggregator;

function ra_rows(): array
{
    return [
        ['estado' => 'pagado',    'cliente' => 'Ana', 'total' => 100],
        ['estado' => 'pagado',    'cliente' => 'Ana', 'total' => 50],
        ['estado' => 'pendiente', 'cliente' => 'Beto', 'total' => 30],
    ];
}

function ra_columns(): array
{
    return [
        ['name' => 'estado', 'label' => 'Estado', 'type' => 'text'],
        ['name' => 'total',  'label' => 'Total',  'type' => 'money'],
    ];
}

test('listado plano sin tratamientos devuelve filas tal cual', function (): void {
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), []);
    assert_same(3, count($out['rows']));
    assert_same('money', $out['columns'][1]['format']);
    assert_same(100, $out['rows'][0]['total']);
    assert_same([], $out['totals']);
});

test('agrupa por estado y suma total', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [['op' => 'sum', 'column' => 'total']],
        'order' => ['by' => 'estado', 'dir' => 'asc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    assert_same(2, count($out['rows']));
    assert_same('pagado', $out['rows'][0]['estado']);
    assert_same(150.0, $out['rows'][0]['sum_total']);
    assert_same(30.0, $out['rows'][1]['sum_total']);

    assert_same('estado', $out['columns'][0]['name']);
    assert_same('sum_total', $out['columns'][1]['name']);
    assert_same('Suma de Total', $out['columns'][1]['label']);
    assert_same('money', $out['columns'][1]['format']);

    assert_same(180.0, $out['totals'][0]['value']);
    assert_same('money', $out['totals'][0]['format']);
});

test('count cuenta filas del grupo y se formatea como number', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [['op' => 'count', 'column' => '']],
        'order' => ['by' => 'estado', 'dir' => 'desc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    assert_same('pendiente', $out['rows'][0]['estado']);
    assert_same(1, $out['rows'][0]['count']);
    assert_same(2, $out['rows'][1]['count']);
    assert_same('number', $out['columns'][1]['format']);
    assert_same('Cantidad', $out['columns'][1]['label']);
    assert_same(3, $out['totals'][0]['value']);
});

test('avg, min y max operan sobre el grupo', function (): void {
    $trat = [
        'group_by' => ['estado'],
        'aggregations' => [
            ['op' => 'avg', 'column' => 'total'],
            ['op' => 'min', 'column' => 'total'],
            ['op' => 'max', 'column' => 'total'],
        ],
        'order' => ['by' => 'estado', 'dir' => 'asc'],
    ];
    $out = (new ReporteAggregator())->apply(ra_rows(), ra_columns(), $trat);

    assert_same(75.0, $out['rows'][0]['avg_total']);
    assert_same(50.0, $out['rows'][0]['min_total']);
    assert_same(100.0, $out['rows'][0]['max_total']);
});
