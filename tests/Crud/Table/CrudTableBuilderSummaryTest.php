<?php

declare(strict_types=1);

use App\Application\Services\CrudTableBuilder;
use App\Domain\Entities\CrudResourceDefinition;
use App\Kernel\Helpers\Paginator;

function tb_flat_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID'],
                ['name' => 'nombre', 'label' => 'Nombre'],
                ['name' => 'precio_venta', 'label' => 'Precio', 'format' => 'money'],
                ['name' => 'stock_actual', 'label' => 'Stock'],
            ],
            'summaries' => [
                ['column' => 'precio_venta', 'type' => 'sum', 'format' => 'money', 'label' => 'Suma precio'],
                ['column' => 'stock_actual', 'type' => 'sum', 'label' => 'Suma stock'],
            ],
        ],
    ]);
}

function tb_paginator(): Paginator
{
    return new Paginator(total: 0, perPage: 15, currentPage: 1, baseUrl: '/admin/crud/demo');
}

test('CrudTableBuilder: pie plano coloca cada summary en su columna y deja el resto vacío', function (): void {
    $builder = new CrudTableBuilder();
    $summaryRow = ['crud_sum_precio_venta' => 1234.5, 'crud_sum_stock_actual' => 42];

    $vm = $builder->build(
        definition: tb_flat_def(),
        rows: [],
        paginator: tb_paginator(),
        total: 0,
        permissions: [],
        query: [],
        groupBy: '',
        summaryRow: $summaryRow
    );

    assert_true(isset($vm['summaryRow']['_formatted']), 'falta _formatted en summaryRow');
    $cells = $vm['summaryRow']['_formatted'];
    assert_same('$1,234.50', $cells['precio_venta'] ?? null, 'sum precio formateado money');
    assert_same(42, $cells['stock_actual'] ?? null, 'sum stock sin formato');
    assert_true(!array_key_exists('id', $cells), 'columnas sin summary no aparecen');
    assert_true(!array_key_exists('nombre', $cells), 'columnas sin summary no aparecen');
});

test('CrudTableBuilder: plano sin summaries devuelve pie vacío', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => ['group_by' => '', 'columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tb_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    assert_same([], $vm['summaryRow'], 'pie vacío sin summaries');
});

test('CrudTableBuilder: modo agrupado conserva el pie por alias (sin regresión)', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => 'nombre',
            'columns' => [['name' => 'nombre', 'label' => 'Nombre'], ['name' => 'precio_venta', 'label' => 'Precio']],
            'summaries' => [['column' => 'precio_venta', 'type' => 'sum', 'label' => 'Total']],
        ],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tb_paginator(), total: 0,
        permissions: [], query: [], groupBy: 'nombre',
        summaryRow: ['crud_sum_precio_venta' => 99]
    );
    assert_same(99, $vm['summaryRow']['_formatted']['crud_sum_precio_venta'] ?? null, 'pie agrupado por alias intacto');
});
