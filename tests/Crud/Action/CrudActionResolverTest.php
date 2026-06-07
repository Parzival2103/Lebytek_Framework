<?php

declare(strict_types=1);

use App\Application\Services\CrudActionResolver;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;

function resolver_def(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'eventos', 'table' => 'dom_eventos', 'primary_key' => 'id', 'permission_prefix' => 'eventos'],
        'actions' => [
            'row' => [
                ['name' => 'show', 'type' => 'builtin'],
                ['name' => 'autorizar', 'type' => 'handler', 'handler' => 'evt_auth',
                 'label' => 'Autorizar', 'icon' => 'bi-check2', 'permission' => 'autorizar',
                 'visible_when' => ['status' => 'pendiente'], 'confirm' => '¿Autorizar?'],
                ['name' => 'contrato', 'type' => 'link', 'route' => '/admin/eventos/{id}/contrato',
                 'permission' => 'contrato.ver'],
            ],
            'bulk' => [
                ['name' => 'activar', 'type' => 'handler', 'handler' => 'evt_bulk', 'permission' => 'editar'],
            ],
        ],
    ]);
}

test('CrudActionResolver: visibleRowActions filters by visible_when and permission', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;

    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'pendiente'], $allow);
    $names = array_map(static fn(array $a): string => $a['name'], $vm);
    assert_same(['show', 'autorizar', 'contrato'], $names);

    // autorizar hidden when status != pendiente
    $vm2 = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'autorizado'], $allow);
    $names2 = array_map(static fn(array $a): string => $a['name'], $vm2);
    assert_same(['show', 'contrato'], $names2);
});

test('CrudActionResolver: permission denial hides the action', function (): void {
    $resolver = new CrudActionResolver();
    $deny = static fn(string $slug): bool => $slug !== 'eventos.autorizar';
    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 7, 'status' => 'pendiente'], $deny);
    $names = array_map(static fn(array $a): string => $a['name'], $vm);
    assert_same(['show', 'contrato'], $names);
});

test('CrudActionResolver: handler view-model carries endpoint, link carries substituted route', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;
    $vm = $resolver->visibleRowActions(resolver_def(), ['id' => 42, 'status' => 'pendiente'], $allow);

    $auth = $vm[1];
    assert_same('autorizar', $auth['name']);
    assert_same('handler', $auth['type']);
    assert_same('/admin/crud/eventos/42/accion/autorizar', $auth['endpoint']);
    assert_same('¿Autorizar?', $auth['confirm']);

    $link = $vm[2];
    assert_same('link', $link['type']);
    assert_same('/admin/eventos/42/contrato', $link['href']);
});

test('CrudActionResolver: permittedBulkActions respects permissions', function (): void {
    $resolver = new CrudActionResolver();
    $allow = static fn(string $slug): bool => true;
    $bulk = $resolver->permittedBulkActions(resolver_def(), $allow);
    assert_same(1, count($bulk));
    assert_same('activar', $bulk[0]['name']);
    assert_same('/admin/crud/eventos/accion-masiva/activar', $bulk[0]['endpoint']);

    $deny = static fn(string $slug): bool => false;
    assert_same([], $resolver->permittedBulkActions(resolver_def(), $deny));
});

test('CrudActionResolver: resolveExecutable returns the row action or throws', function (): void {
    $resolver = new CrudActionResolver();
    $a = $resolver->resolveExecutable(resolver_def(), 'autorizar');
    assert_same('autorizar', $a->name());

    assert_throws(ValidationException::class, function () use ($resolver): void {
        $resolver->resolveExecutable(resolver_def(), 'fantasma');
    });
});

test('CrudActionResolver: resolveBulkExecutable returns the bulk action or throws', function (): void {
    $resolver = new CrudActionResolver();
    $a = $resolver->resolveBulkExecutable(resolver_def(), 'activar');
    assert_same('activar', $a->name());

    assert_throws(ValidationException::class, function () use ($resolver): void {
        $resolver->resolveBulkExecutable(resolver_def(), 'nope');
    });
});
