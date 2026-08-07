<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudActionService;
use Lebytek\Framework\Domain\Entities\Crud\CrudActionDefinition;
use Lebytek\Framework\Domain\Exceptions\AccesoException;

test('resolveExecutablePermission expande permission relativa al prefix', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'toggle',
        'type' => 'handler',
        'handler' => 'p_toggle',
        'permission' => 'editar',
    ]);
    assert_same(
        'demo_productos.editar',
        CrudActionService::resolveExecutablePermission($action, 'demo_productos')
    );
});

test('resolveExecutablePermission acepta slug absoluto con punto', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'wa',
        'type' => 'handler',
        'handler' => 'enviar_whatsapp_demo',
        'permission' => 'integrations.enviar',
    ]);
    assert_same(
        'integrations.enviar',
        CrudActionService::resolveExecutablePermission($action, 'demo_clientes')
    );
});

test('resolveExecutablePermission falla cerrado sin permission (C2)', function (): void {
    $action = CrudActionDefinition::fromArray([
        'name' => 'toggle',
        'type' => 'handler',
        'handler' => 'p_toggle',
    ]);
    assert_throws(AccesoException::class, function () use ($action): void {
        CrudActionService::resolveExecutablePermission($action, 'demo_productos');
    });
});

test('resolveExecutablePermission falla cerrado con permission vacía', function (): void {
    // fromArray trata '' como null
    $action = CrudActionDefinition::fromArray([
        'name' => 'pagar',
        'type' => 'transition',
        'to' => 'pagado',
        'permission' => '',
    ]);
    assert_throws(AccesoException::class, function () use ($action): void {
        CrudActionService::resolveExecutablePermission($action, 'demo_pedidos');
    });
});
