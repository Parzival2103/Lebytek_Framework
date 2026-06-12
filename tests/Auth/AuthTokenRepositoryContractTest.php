<?php

declare(strict_types=1);

use App\Domain\Entities\AuthToken;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function token_nuevo(array $overrides = []): AuthToken
{
    return AuthToken::desdeFila(array_merge([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'token-claro'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ], $overrides));
}

test('Contrato: buscarVigentePorHash encuentra solo tokens vigentes del tipo pedido', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo());

    $hash = hash('sha256', 'token-claro');
    assert_true($repo->buscarVigentePorHash($hash, AuthToken::TIPO_RECUPERACION) !== null);
    assert_null($repo->buscarVigentePorHash($hash, AuthToken::TIPO_VERIFICACION), 'otro tipo no debe matchear');
    assert_null($repo->buscarVigentePorHash(hash('sha256', 'otro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: un token expirado no es vigente', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['expira_en' => date('Y-m-d H:i:s', time() - 60)]));

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'token-claro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: marcarUsado consume el token (deja de ser vigente)', function (): void {
    $repo = new FakeAuthTokenRepository();
    $id   = $repo->guardar(token_nuevo());

    $repo->marcarUsado($id);

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'token-claro'), AuthToken::TIPO_RECUPERACION));
});

test('Contrato: invalidarDeUsuario invalida solo el mismo usuario+tipo', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'a')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'b'), 'tipo' => AuthToken::TIPO_VERIFICACION]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'c'), 'usuario_id' => 2]));

    $repo->invalidarDeUsuario(1, AuthToken::TIPO_RECUPERACION);

    assert_null($repo->buscarVigentePorHash(hash('sha256', 'a'), AuthToken::TIPO_RECUPERACION));
    assert_true($repo->buscarVigentePorHash(hash('sha256', 'b'), AuthToken::TIPO_VERIFICACION) !== null, 'otro tipo sigue vigente');
    assert_true($repo->buscarVigentePorHash(hash('sha256', 'c'), AuthToken::TIPO_RECUPERACION) !== null, 'otro usuario sigue vigente');
});

test('Contrato: contarRecientes cuenta emisiones por usuario+tipo dentro de la ventana', function (): void {
    $repo = new FakeAuthTokenRepository();
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'a')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'b')]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'c'), 'tipo' => AuthToken::TIPO_VERIFICACION]));
    $repo->guardar(token_nuevo(['token_hash' => hash('sha256', 'd'), 'created_at' => date('Y-m-d H:i:s', time() - 7200)]));

    assert_same(2, $repo->contarRecientes(1, AuthToken::TIPO_RECUPERACION, 60));
    assert_same(1, $repo->contarRecientes(1, AuthToken::TIPO_VERIFICACION, 60));
    assert_same(0, $repo->contarRecientes(2, AuthToken::TIPO_RECUPERACION, 60));
});
