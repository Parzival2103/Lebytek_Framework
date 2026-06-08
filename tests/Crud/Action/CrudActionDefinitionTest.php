<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudActionDefinition;

test('CrudActionDefinition: parses a handler action', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'regenerar', 'type' => 'handler', 'handler' => 'evt_regen',
        'label' => 'Regenerar', 'icon' => 'bi-arrow-repeat', 'method' => 'POST',
        'permission' => 'eventos.doc.regenerar', 'confirm' => '¿Regenerar?',
    ]);
    assert_same('regenerar', $a->name());
    assert_same('handler', $a->type());
    assert_same('evt_regen', $a->handler());
    assert_same('Regenerar', $a->label());
    assert_same('bi-arrow-repeat', $a->icon());
    assert_same('POST', $a->method());
    assert_same('¿Regenerar?', $a->confirm());
    assert_true($a->isHandler());
    assert_true(!$a->isLink());
    assert_true(!$a->isBuiltin());
});

test('CrudActionDefinition: link defaults method to GET, keeps route', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'contrato', 'type' => 'link', 'route' => '/admin/eventos/{id}/contrato',
    ]);
    assert_true($a->isLink());
    assert_same('GET', $a->method());
    assert_same('/admin/eventos/{id}/contrato', $a->route());
});

test('CrudActionDefinition: handler defaults method to POST and label to name', function (): void {
    $a = CrudActionDefinition::fromArray(['name' => 'sync', 'type' => 'handler', 'handler' => 'h']);
    assert_same('POST', $a->method());
    assert_same('sync', $a->label());
});

test('CrudActionDefinition: resolvePermission expands a suffix against the prefix', function (): void {
    $suffix = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'h', 'permission' => 'editar']);
    assert_same('eventos.editar', $suffix->resolvePermission('eventos'));

    $full = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'h', 'permission' => 'otro.modulo.ver']);
    assert_same('otro.modulo.ver', $full->resolvePermission('eventos'));

    $none = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'link', 'route' => '/r']);
    assert_null($none->resolvePermission('eventos'));
});

test('CrudActionDefinition: isVisibleFor with scalar equality', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'autorizar', 'type' => 'handler', 'handler' => 'h',
        'visible_when' => ['status' => 'pendiente'],
    ]);
    assert_true($a->isVisibleFor(['status' => 'pendiente']));
    assert_true(!$a->isVisibleFor(['status' => 'autorizado']));
    assert_true(!$a->isVisibleFor([]));
});

test('CrudActionDefinition: isVisibleFor with list membership and no condition', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'visible_when' => ['status' => ['pendiente', 'revision']],
    ]);
    assert_true($a->isVisibleFor(['status' => 'revision']));
    assert_true(!$a->isVisibleFor(['status' => 'cerrado']));

    $always = CrudActionDefinition::fromArray(['name' => 'y', 'type' => 'handler', 'handler' => 'h']);
    assert_true($always->isVisibleFor([]));
});

test('CrudActionDefinition: isEnabledFor defaults true and honors enabled_when', function (): void {
    $a = CrudActionDefinition::fromArray([
        'name' => 'x', 'type' => 'handler', 'handler' => 'h',
        'enabled_when' => ['bloqueado' => 0],
    ]);
    assert_true($a->isEnabledFor(['bloqueado' => 0]));
    assert_true(!$a->isEnabledFor(['bloqueado' => 1]));

    $b = CrudActionDefinition::fromArray(['name' => 'y', 'type' => 'handler', 'handler' => 'h']);
    assert_true($b->isEnabledFor([]));
});

test('CrudActionDefinition: builtin predicate', function (): void {
    $a = CrudActionDefinition::fromArray(['name' => 'edit', 'type' => 'builtin']);
    assert_true($a->isBuiltin());
});

test('CrudActionDefinition: parses guard key for transition actions', function (): void {
    $a = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray([
        'name' => 'autorizar',
        'type' => 'transition',
        'to' => 'autorizado',
        'guard' => 'evento_autorizacion',
    ]);
    assert_same('autorizado', $a->to());
    assert_same('evento_autorizacion', $a->guard());
    assert_true($a->isTransition());
});

test('CrudActionDefinition: guard is null when absent or empty', function (): void {
    $a = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray(['name' => 'edit', 'type' => 'builtin']);
    assert_null($a->guard());
    $b = \App\Domain\Entities\Crud\CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'transition', 'to' => 't', 'guard' => '']);
    assert_null($b->guard());
});
