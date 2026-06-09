<?php
declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition::columnNames une PK, columnas de lista y campos de form sin duplicar', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'demo', 'table' => 'dom_demo', 'primary_key' => 'id', 'permission_prefix' => 'demo'],
        'list' => ['columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'estado', 'label' => 'Estado'],
        ]],
        'form' => ['fields' => [
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'fecha_inicio', 'label' => 'Inicio'],
            ['name' => 'fecha_fin', 'label' => 'Fin'],
        ]],
    ]);

    $cols = $def->columnNames();
    assert_true(in_array('id', $cols, true), 'incluye PK');
    assert_true(in_array('cliente', $cols, true), 'incluye columna+campo');
    assert_true(in_array('estado', $cols, true), 'incluye columna de lista');
    assert_true(in_array('fecha_inicio', $cols, true), 'incluye campo de form');
    assert_true(in_array('fecha_fin', $cols, true), 'incluye campo de form');
    assert_same(count($cols), count(array_unique($cols)), 'sin duplicados');
});
