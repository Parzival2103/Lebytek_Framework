<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CRUD Engine — registro de handlers (whitelist)
|--------------------------------------------------------------------------
| Las claves son strings simples referenciadas en config/cruds/*.json:
|
|   "hooks": { "handler": "clientes" }
|
| NUNCA uses FQCN directamente en JSON.
|
| El registro ahora admite, además de hooks de escritura, los handlers de:
|   - acciones de fila/bulk      (CrudActionHandlerInterface)
|   - guards de transición       (CrudTransitionGuardInterface)
|   - validadores de formulario  (CrudValidatorInterface)
|   - scopes de listado          (CrudListScopeInterface)
| Una misma clave puede mapear a una clase que implemente varias interfaces.
|--------------------------------------------------------------------------
*/

return [
    'demo_producto_toggle' => \App\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class,
    'demo_producto_state_guard' => \App\Application\Crud\Handlers\DemoProductoStateGuard::class,

    'demo_pedido_total'        => \App\Application\Crud\Handlers\DemoPedidoTotalValidator::class,
    'demo_pedido_pagar_guard'  => \App\Application\Crud\Handlers\DemoPedidoPagarGuard::class,
    'demo_cliente_contacto'    => \App\Application\Crud\Handlers\DemoClienteContactoValidator::class,

    // Ejemplo (descomenta y crea la clase al implementar lógica real):
    // 'clientes'        => \App\Application\Crud\Handlers\ClientesHandler::class,
    // 'anticipo_minimo' => \App\Application\Crud\Handlers\AnticipoMinimoValidator::class,
];
