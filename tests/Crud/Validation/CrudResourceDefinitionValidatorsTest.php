<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition: formValidators is empty by default', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
    ]);
    assert_same([], $def->formValidators());
});

test('CrudResourceDefinition: formValidators parses form.validators list of strings', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'x', 'title' => 'X', 'table' => 'dom_x', 'primary_key' => 'id', 'permission_prefix' => 'x'],
        'form' => ['validators' => ['anticipo_minimo', 'fecha_disponible', '', 123]],
    ]);
    assert_same(['anticipo_minimo', 'fecha_disponible'], $def->formValidators());
});
