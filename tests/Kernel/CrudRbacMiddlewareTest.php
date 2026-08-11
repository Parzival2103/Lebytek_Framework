<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Presentation\Middlewares\CrudRbacMiddleware;

test('CrudRbacMiddleware returns 403 with permiso slug when user lacks access', function (): void {
    if (!class_exists(CrudRbacMiddleware::class)) {
        throw new \RuntimeException(
            'CrudRbacMiddleware missing or not registered — middleware ausente o no registrado (spec M3 U6)'
        );
    }

    $_SESSION['auth_permisos'] = [];
    $_SESSION['auth_roles'] = [];

    $request = new Request('GET', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);

    $middleware = new CrudRbacMiddleware();
    $response = $middleware->handle($request, fn (Request $r) => Response::json(['ok' => true]));

    assert_same(403, $response->getStatusCode(), 'expected 403 before CrudController without demo_clientes.ver');

    $body = $response->getBody();
    assert_true(
        str_contains($body, 'demo_clientes.ver'),
        '403 response must mention required slug demo_clientes.ver (U1/U6)'
    );
});

test('CrudRbacMiddleware returns JSON permiso field for AJAX requests', function (): void {
    $_SESSION['auth_permisos'] = [];
    $_SESSION['auth_roles'] = [];

    $request = new Request(
        'GET',
        '/admin/calendario/demo_citas/eventos',
        [],
        [],
        ['X-Requested-With' => 'XMLHttpRequest']
    );
    $request->setRouteParams(['key' => 'demo_citas']);

    $middleware = new CrudRbacMiddleware();
    $response = $middleware->handle($request, fn (Request $r) => Response::json(['eventos' => []]));

    assert_same(403, $response->getStatusCode());
    $data = json_decode($response->getBody(), true);
    assert_true(is_array($data), 'AJAX 403 must be JSON (U3/U7)');
    assert_same('Acceso denegado.', $data['error'] ?? null);
    assert_same('demo_citas.ver', $data['permiso'] ?? null);
});

test('CrudRbacMiddleware delegates unknown CRUD resource to next handler (ValidationException path)', function (): void {
    $_SESSION['auth_permisos'] = ['anything.ver'];
    $_SESSION['auth_roles'] = [];

    $request = new Request('GET', '/admin/crud/no_existe_xyz');
    $request->setRouteParams(['resource' => 'no_existe_xyz']);

    $middleware = new CrudRbacMiddleware();
    $called = false;
    $response = $middleware->handle($request, function (Request $r) use (&$called) {
        $called = true;
        return Response::json(['delegated' => true]);
    });

    assert_true($called, 'invalid resource must pass through to controller (U4), not RBAC 403');
    assert_same(200, $response->getStatusCode());
});
