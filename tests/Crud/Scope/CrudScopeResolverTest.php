<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudListContext;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudScopeResolver;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudListScopeInterface;

if (!class_exists('FixtureCustomScope')) {
    class FixtureCustomScope implements CrudListScopeInterface
    {
        public function apply(CrudListContext $ctx): void
        {
            $ctx->addCondition('created_by', '=', 99);
        }
    }
}

function scope_def(array $list): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id', 'permission_prefix' => 'clientes'],
        'list' => $list,
    ]);
}

test('CrudScopeResolver: scope owner sin bypass produce OwnerListScope que filtra por userId', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $deny = static fn(string $slug): bool => false;

    $scope = $resolver->resolve($def, 7, $deny);
    assert_true($scope instanceof CrudListScopeInterface, 'devuelve un scope');

    $ctx = new CrudListContext('clientes', 'dom_clientes', 'id', 7, '', []);
    $scope->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => 7]], $ctx->conditions());
});

test('CrudScopeResolver: ownerMeta expande {prefix} en bypass', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $meta = $resolver->ownerMeta($def);
    assert_same('created_by', $meta['column']);
    assert_same('clientes.ver_todos', $meta['bypass']);
});

test('CrudScopeResolver: usuario con bypass obtiene un scope que NO filtra', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']]);
    $allow = static fn(string $slug): bool => $slug === 'clientes.ver_todos';

    $scope = $resolver->resolve($def, 7, $allow);
    $ctx = new CrudListContext('clientes', 'dom_clientes', 'id', 7, '', []);
    $scope->apply($ctx);
    assert_same([], $ctx->conditions(), 'con bypass no hay filtro');
});

test('CrudScopeResolver: scope_handler resuelve la clase registrada', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry(['clientes_owner' => FixtureCustomScope::class]));
    $def = scope_def(['scope_handler' => 'clientes_owner']);
    $scope = $resolver->resolve($def, 7, static fn(string $s): bool => false);
    assert_true($scope instanceof FixtureCustomScope, 'resuelve el handler custom');
});

test('CrudScopeResolver: sin scope devuelve null', function (): void {
    $resolver = new CrudScopeResolver(new CrudHandlerRegistry([]));
    $def = scope_def(['columns' => [['name' => 'id', 'label' => 'ID']]]);
    assert_null($resolver->resolve($def, 7, static fn(string $s): bool => false));
    assert_null($resolver->ownerMeta($def));
});

test('CrudScopeResolver: conditionsToSql traduce = a backtick + placeholder', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => '=', 'value' => 7],
    ]);
    assert_same(['`created_by` = ?'], $where);
    assert_same([7], $params);
});

test('CrudScopeResolver: conditionsToSql expande IN con array', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => 'IN', 'value' => [1, 2, 3]],
    ]);
    assert_same(['`created_by` IN (?, ?, ?)'], $where);
    assert_same([1, 2, 3], $params);
});

test('CrudScopeResolver: conditionsToSql con IN vacío fuerza conjunto vacío', function (): void {
    [$where, $params] = CrudScopeResolver::conditionsToSql([
        ['column' => 'created_by', 'op' => 'IN', 'value' => []],
    ]);
    assert_same(['1 = 0'], $where);
    assert_same([], $params);
});
