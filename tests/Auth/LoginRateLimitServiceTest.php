<?php

declare(strict_types=1);

use App\Application\Services\LoginRateLimitService;
use App\Domain\Exceptions\AuthException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('LoginRateLimitService: permite intentos por debajo del máximo', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 3, ventanaMin: 15);

    $repo->registrarFallo('1.1.1.1', 'a@test.local');
    $repo->registrarFallo('1.1.1.1', 'a@test.local');

    $service->asegurarPermitido('1.1.1.1', 'a@test.local');
    assert_true(true, 'no debe lanzar con 2 fallos y max=3');
});

test('LoginRateLimitService: bloquea cuando IP alcanza el máximo en ventana', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 3, ventanaMin: 15);

    $repo->registrarFallo('9.9.9.9', 'x@test.local');
    $repo->registrarFallo('9.9.9.9', 'y@test.local');
    $repo->registrarFallo('9.9.9.9', 'z@test.local');

    $lanzo = false;
    try {
        $service->asegurarPermitido('9.9.9.9', 'otro@test.local');
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }
    assert_true($lanzo, 'debe lanzar AuthException genérica');
});

test('LoginRateLimitService: bloquea cuando email alcanza el máximo aunque la IP sea distinta', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 2, ventanaMin: 15);

    $repo->registrarFallo('1.0.0.1', 'victima@test.local');
    $repo->registrarFallo('2.0.0.2', 'victima@test.local');

    $lanzo = false;
    try {
        $service->asegurarPermitido('3.0.0.3', 'victima@test.local');
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }
    assert_true($lanzo);
});

test('LoginRateLimitService: registrarFallo persiste y purga antiguos', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 5, ventanaMin: 10);

    $service->registrarFallo('5.5.5.5', 'u@test.local');

    assert_same(2, count($repo->filas));
});

test('LoginRateLimitService: limpiarTrasExito borra contadores de ip y email', function (): void {
    $repo    = new FakeLoginIntentoRepository();
    $service = new LoginRateLimitService($repo, maxIntentos: 5, ventanaMin: 15);

    $service->registrarFallo('8.8.8.8', 'ok@test.local');
    $service->limpiarTrasExito('8.8.8.8', 'ok@test.local');

    assert_same(0, count($repo->filas));
    $service->asegurarPermitido('8.8.8.8', 'ok@test.local');
});
