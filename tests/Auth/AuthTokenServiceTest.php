<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\AuthTokenService;
use Lebytek\Framework\Domain\Entities\AuthToken;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

test('AuthTokenService: emitir devuelve token claro de 64 hex y persiste solo el hash', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    $token = $service->emitir(7, AuthToken::TIPO_VERIFICACION, 1440);

    assert_true($token !== null);
    assert_same(64, strlen($token));
    assert_true(ctype_xdigit($token));
    assert_same(1, count($repo->tokens));
    $guardado = array_values($repo->tokens)[0];
    assert_same(hash('sha256', $token), $guardado->tokenHash());
    assert_same(7, $guardado->usuarioId());
    assert_same(AuthToken::TIPO_VERIFICACION, $guardado->tipo());
    assert_true($guardado->expiraEn() > date('Y-m-d H:i:s'), 'debe expirar en el futuro');
});

test('AuthTokenService: emitir invalida los tokens previos del mismo usuario+tipo', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    $token1 = $service->emitir(7, AuthToken::TIPO_RECUPERACION, 60);
    $token2 = $service->emitir(7, AuthToken::TIPO_RECUPERACION, 60);

    assert_null($repo->buscarVigentePorHash(hash('sha256', $token1), AuthToken::TIPO_RECUPERACION), 'el previo queda invalidado');
    assert_true($repo->buscarVigentePorHash(hash('sha256', $token2), AuthToken::TIPO_RECUPERACION) !== null);
});

test('AuthTokenService: la emisión que excede el máximo por hora devuelve null y no persiste', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 3);

    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);

    assert_null($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60), '4ª emisión en la hora debe throttlearse');
    assert_same(3, count($repo->tokens));
});

test('AuthTokenService: el throttle es independiente por tipo', function (): void {
    $repo    = new FakeAuthTokenRepository();
    $service = new AuthTokenService($repo, 1);

    assert_true($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60) !== null);
    assert_null($service->emitir(7, AuthToken::TIPO_RECUPERACION, 60));
    assert_true($service->emitir(7, AuthToken::TIPO_VERIFICACION, 60) !== null, 'otro tipo no comparte throttle');
});
