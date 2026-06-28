<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\AuthToken;

test('AuthToken: desdeFila hidrata todos los campos', function (): void {
    $token = AuthToken::desdeFila([
        'id'         => 5,
        'usuario_id' => 9,
        'tipo'       => 'recuperacion',
        'token_hash' => str_repeat('a', 64),
        'expira_en'  => '2030-01-01 00:00:00',
        'usado_en'   => null,
        'created_at' => '2026-06-12 10:00:00',
    ]);

    assert_same(5, $token->id());
    assert_same(9, $token->usuarioId());
    assert_same('recuperacion', $token->tipo());
    assert_same(str_repeat('a', 64), $token->tokenHash());
    assert_same('2030-01-01 00:00:00', $token->expiraEn());
    assert_null($token->usadoEn());
    assert_same('2026-06-12 10:00:00', $token->createdAt());
});

test('AuthToken: vigente si no está usado y no ha expirado', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_VERIFICACION,
        tokenHash: str_repeat('b', 64),
        expiraEn:  date('Y-m-d H:i:s', time() + 3600)
    );
    assert_true($token->estaVigente());
});

test('AuthToken: no vigente si expiró', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_VERIFICACION,
        tokenHash: str_repeat('b', 64),
        expiraEn:  date('Y-m-d H:i:s', time() - 60)
    );
    assert_true(!$token->estaVigente(), 'token expirado no debe estar vigente');
});

test('AuthToken: no vigente si ya fue usado; marcarUsado es clon inmutable', function (): void {
    $token = new AuthToken(
        usuarioId: 1,
        tipo:      AuthToken::TIPO_RECUPERACION,
        tokenHash: str_repeat('c', 64),
        expiraEn:  date('Y-m-d H:i:s', time() + 3600)
    );

    $usado = $token->marcarUsado('2026-06-12 11:00:00');

    assert_true($token->estaVigente(), 'el original no debe mutar');
    assert_true(!$usado->estaVigente(), 'el clon usado no está vigente');
    assert_same('2026-06-12 11:00:00', $usado->usadoEn());
});
