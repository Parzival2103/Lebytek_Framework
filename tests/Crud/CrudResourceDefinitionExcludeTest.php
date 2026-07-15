<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition parsea list.exclude', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => [
            'columns' => [['name' => 'estado', 'label' => 'Estado']],
            'exclude' => [
                ['field' => 'estado', 'values' => ['pendiente', 'demo_baja']],
                ['field' => '', 'values' => ['x']],
                ['field' => 'estado', 'values' => []],
            ],
        ],
    ]);

    assert_same([
        ['field' => 'estado', 'values' => ['pendiente', 'demo_baja']],
    ], $def->listExcludes());
});
