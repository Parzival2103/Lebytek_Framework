<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Reporte\ReporteFuente;

function rf_config(): array
{
    return [
        'fuente' => ['key' => 'pedidos', 'title' => 'Pedidos', 'resource' => 'demo_pedidos', 'icon' => 'bi-receipt'],
        'modos'  => ['coleccion'],
        'expose' => [
            'columns' => [
                ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text'],
                ['name' => 'total',   'label' => 'Total',   'type' => 'money', 'treatments' => ['sum', 'avg', 'min', 'max']],
            ],
            'group_by' => ['cliente'],
            'order_by' => ['total'],
            'filters'  => [['field' => 'estado', 'label' => 'Estado']],
            'period'   => ['field' => 'fecha', 'label' => 'Fecha', 'presets' => ['mes', 'todo']],
            'max_rows' => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => []],
    ];
}

test('ReporteFuente expone metadatos básicos', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_same('pedidos', $f->key());
    assert_same('Pedidos', $f->title());
    assert_same('demo_pedidos', $f->resource());
    assert_same(5000, $f->maxRows());
});

test('ReporteFuente conoce columnas, tipos y etiquetas', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_true($f->hasColumn('total'));
    assert_true(!$f->hasColumn('inexistente'));
    assert_same('money', $f->columnType('total'));
    assert_same('Cliente', $f->columnLabel('cliente'));
});

test('ReporteFuente valida tratamientos por columna', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_true($f->allowsTreatment('total', 'sum'));
    assert_true(!$f->allowsTreatment('total', 'mediana'));
    assert_true(!$f->allowsTreatment('cliente', 'sum'));
});

test('ReporteFuente expone group_by, order_by, filtros y periodo', function (): void {
    $f = ReporteFuente::fromArray('pedidos', rf_config());
    assert_same(['cliente'], $f->groupBy());
    assert_same(['total'], $f->orderBy());
    assert_true($f->hasFilter('estado'));
    assert_true($f->hasPeriod());
    assert_same('fecha', $f->periodField());
    assert_same(['mes', 'todo'], $f->periodPresets());
    assert_same(['tabla_estadistica'], $f->templatesFor('coleccion'));
});
