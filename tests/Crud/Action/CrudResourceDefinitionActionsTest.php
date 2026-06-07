<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudActionDefinition;

test('CrudResourceDefinition: no actions block falls back to list.actions builtins', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'list' => ['actions' => ['show', 'edit', 'delete']],
    ]);
    assert_true(!$def->hasActionsBlock());
    $names = array_map(static fn(CrudActionDefinition $a): string => $a->name(), $def->rowActions());
    assert_same(['show', 'edit', 'delete'], $names);
    foreach ($def->rowActions() as $a) {
        assert_true($a->isBuiltin(), 'fallback actions must be builtin');
    }
    assert_same([], $def->bulkActions());
});

test('CrudResourceDefinition: parses row and bulk actions', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'actions' => [
            'row' => [
                ['name' => 'edit', 'type' => 'builtin'],
                ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle', 'permission' => 'editar'],
            ],
            'bulk' => [
                ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk', 'permission' => 'editar'],
            ],
        ],
    ]);
    assert_true($def->hasActionsBlock());
    assert_same(2, count($def->rowActions()));
    assert_same('toggle', $def->rowActions()[1]->name());
    assert_same(1, count($def->bulkActions()));
    assert_same('activar', $def->bulkActions()[0]->name());
});

test('CrudResourceDefinition: empty actions block yields no row actions', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'p', 'table' => 'dom_p', 'primary_key' => 'id'],
        'actions' => [],
    ]);
    assert_true($def->hasActionsBlock());
    assert_same([], $def->rowActions());
    assert_same([], $def->bulkActions());
});
