<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\AuthTokenService;
use Lebytek\Framework\Application\Services\CorreoAuthService;
use Lebytek\Framework\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use Lebytek\Framework\Application\UseCases\Auth\VerificarCorreoUseCase;
use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\ValueObjects\Email;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function usuario_sin_verificar(int $id, string $email = 'ana@test.local'): Usuario
{
    return new Usuario(
        nombre:       'Ana',
        apellido:     'Lopez',
        email:        new Email($email),
        passwordHash: 'hash',
        activo:       false,
        id:           $id
    );
}

function token_verificacion_guardado(FakeAuthTokenRepository $repo, int $usuarioId, string $claro, array $overrides = []): int
{
    return $repo->guardar(AuthToken::desdeFila(array_merge([
        'usuario_id' => $usuarioId,
        'tipo'       => AuthToken::TIPO_VERIFICACION,
        'token_hash' => hash('sha256', $claro),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ], $overrides)));
}

test('Verificar: token vigente activa la cuenta, marca verificado y consume el token', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);
    token_verificacion_guardado($tokenRepo, 5, 'tok-claro');

    $useCase = new VerificarCorreoUseCase($tokenRepo, $usuarioRepo);
    $useCase->execute('tok-claro');

    assert_same([5], $usuarioRepo->verificados, 'marcarEmailVerificado del usuario 5');
    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-claro'), AuthToken::TIPO_VERIFICACION), 'token consumido');
});

test('Verificar: token inexistente, vencido, usado o de otro tipo lanza ValidationException', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);

    $useCase = new VerificarCorreoUseCase($tokenRepo, $usuarioRepo);

    assert_throws(ValidationException::class, fn() => $useCase->execute('no-existe'), 'inexistente');

    token_verificacion_guardado($tokenRepo, 5, 'vencido', ['expira_en' => date('Y-m-d H:i:s', time() - 60)]);
    assert_throws(ValidationException::class, fn() => $useCase->execute('vencido'), 'vencido');

    $idUsado = token_verificacion_guardado($tokenRepo, 5, 'usado');
    $tokenRepo->marcarUsado($idUsado);
    assert_throws(ValidationException::class, fn() => $useCase->execute('usado'), 'usado');

    token_verificacion_guardado($tokenRepo, 5, 'otro-tipo', ['tipo' => AuthToken::TIPO_RECUPERACION]);
    assert_throws(ValidationException::class, fn() => $useCase->execute('otro-tipo'), 'tipo recuperacion no verifica correo');

    assert_same([], $usuarioRepo->verificados, 'nadie quedó verificado');
});

function reenviar_armar(): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();
    $useCase     = new ReenviarVerificacionUseCase(
        usuarioRepo:        $usuarioRepo,
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             fake_correo_auth_service($mailer),
        verificacionTtlMin: 1440
    );
    return [$useCase, $usuarioRepo, $tokenRepo, $mailer];
}

test('Reenviar: usuario pendiente recibe token nuevo y el previo queda invalidado', function (): void {
    [$useCase, $usuarioRepo, $tokenRepo, $mailer] = reenviar_armar();
    $usuarioRepo->usuarios[5] = usuario_sin_verificar(5);
    token_verificacion_guardado($tokenRepo, 5, 'previo');

    $useCase->execute('ana@test.local');

    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'previo'), AuthToken::TIPO_VERIFICACION), 'previo invalidado');
    assert_same(2, count($tokenRepo->tokens));
    assert_same(1, count($mailer->enviados));
});

test('Reenviar: email inexistente responde silencioso y no envía nada (anti-enumeración)', function (): void {
    [$useCase, , $tokenRepo, $mailer] = reenviar_armar();

    $useCase->execute('nadie@test.local');
    $useCase->execute('esto-no-es-un-email');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Reenviar: usuario activo o ya verificado no recibe correo', function (): void {
    [$useCase, $usuarioRepo, $tokenRepo, $mailer] = reenviar_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'activa@test.local');
    $usuarioRepo->usuarios[2] = new Usuario(
        nombre: 'Bea', apellido: 'Ruiz', email: new Email('verificada@test.local'),
        passwordHash: 'hash', activo: false,
        emailVerificadoEn: new \DateTimeImmutable('2026-01-01 00:00:00'), id: 2
    );

    $useCase->execute('activa@test.local');
    $useCase->execute('verificada@test.local');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});
