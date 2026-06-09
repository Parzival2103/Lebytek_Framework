<?php

declare(strict_types=1);

use App\Domain\Entities\CrudResourceDefinition;

test('CrudResourceDefinition: list.scope owner se expone como array', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => ['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']],
    ]);
    $scope = $def->listScope();
    assert_true(is_array($scope), 'scope debe ser array');
    assert_same('owner', $scope['type']);
    assert_same('created_by', $scope['column']);
    assert_same('{prefix}.ver_todos', $scope['bypass_permission']);
    assert_null($def->listScopeHandler());
});

test('CrudResourceDefinition: list.scope_handler se expone como string', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => ['scope_handler' => 'clientes_owner'],
    ]);
    assert_same('clientes_owner', $def->listScopeHandler());
    assert_null($def->listScope());
});

test('CrudResourceDefinition: sin scope ambos accesores son null', function (): void {
    $def = CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'd', 'table' => 'dom_d', 'primary_key' => 'id', 'permission_prefix' => 'd'],
        'list' => ['columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
    assert_null($def->listScope());
    assert_null($def->listScopeHandler());
});
