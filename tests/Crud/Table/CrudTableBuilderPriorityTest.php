<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudTableBuilder;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Kernel\Helpers\Paginator;

function tbp_paginator(): Paginator
{
    return new Paginator(total: 0, perPage: 15, currentPage: 1, baseUrl: '/admin/crud/demo');
}

function tbp_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID', 'priority' => 1],
                ['name' => 'nombre', 'label' => 'Nombre'],
                ['name' => 'precio_venta', 'label' => 'Precio', 'format' => 'money', 'priority' => 3],
            ],
        ],
    ]);
}

test('CrudTableBuilder: propaga priority como int cuando está declarado', function (): void {
    $builder = new CrudTableBuilder();
    $vm = $builder->build(
        definition: tbp_def(), rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    $cols = $vm['columns'];
    assert_same(1, $cols[0]['priority'] ?? null, 'columna id propaga priority=1');
    assert_same(3, $cols[2]['priority'] ?? null, 'columna precio_venta propaga priority=3');
});

test('CrudTableBuilder: omite priority cuando no está declarado', function (): void {
    $builder = new CrudTableBuilder();
    $vm = $builder->build(
        definition: tbp_def(), rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    $cols = $vm['columns'];
    assert_true(!array_key_exists('priority', $cols[1]), 'columna nombre no tiene clave priority');
});

test('CrudTableBuilder: priority no-numérico se ignora', function (): void {
    $builder = new CrudTableBuilder();
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'group_by' => '',
            'columns' => [
                ['name' => 'id', 'label' => 'ID', 'priority' => 'alta'],
            ],
        ],
    ]);
    $vm = $builder->build(
        definition: $def, rows: [], paginator: tbp_paginator(), total: 0,
        permissions: [], query: [], groupBy: '', summaryRow: []
    );
    assert_true(!array_key_exists('priority', $vm['columns'][0]), 'priority no-numérico se ignora');
});
