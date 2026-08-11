<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Http\Router;
use Lebytek\Framework\Presentation\Controllers\Api\HealthController;
use Lebytek\Framework\Presentation\Middlewares\AuthMiddleware;

$root = dirname(__DIR__, 2);

test('HealthController::health returns 200 JSON ok without session', function (): void {
    if (!method_exists(HealthController::class, 'health')) {
        throw new \RuntimeException(
            'HealthController::health() missing — add method returning {"status":"ok"} per spec M4'
        );
    }

    $controller = new HealthController();
    $response = $controller->health(new Request('GET', '/api/health'));
    assert_same(200, $response->getStatusCode());

    $data = json_decode($response->getBody(), true);
    assert_true(is_array($data), 'health response must be JSON object');
    assert_same('ok', $data['status'] ?? null);
    assert_true(strlen($response->getBody()) <= 200, 'health payload must be <= 200 bytes (U4)');
});

test('AuthMiddleware blocks unauthenticated /api/ping (not public liveness)', function (): void {
    $middleware = new AuthMiddleware();
    $request = new Request('GET', '/api/ping');
    $response = $middleware->handle($request, fn (Request $r) => (new HealthController())->ping($r));

    assert_same(302, $response->getStatusCode());
    assert_same('/login', $response->getHeaders()['Location'] ?? null);
});

test('Router dispatch serves /api/health without session when route is public', function () use ($root): void {
    $router = new Router();
    require $root . '/routes/api.php';

    ob_start();
    $router->dispatch(new Request('GET', '/api/health'));
    $body = ob_get_clean();

    $data = json_decode($body, true);
    assert_true(
        is_array($data) && ($data['status'] ?? null) === 'ok',
        'GET /api/health absent or behind AuthMiddleware — register before $router->group per spec M4 '
        . '(docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md)'
    );
});

test('Router dispatch does not return 200 JSON ok for /api/ping without session', function () use ($root): void {
    $router = new Router();
    require $root . '/routes/api.php';

    ob_start();
    $router->dispatch(new Request('GET', '/api/ping'));
    $body = ob_get_clean();

    $data = json_decode($body, true);
    assert_true(
        !is_array($data) || ($data['status'] ?? null) !== 'ok',
        'unauthenticated /api/ping must NOT return {"status":"ok"} — use /api/health for LB/cron'
    );
});
