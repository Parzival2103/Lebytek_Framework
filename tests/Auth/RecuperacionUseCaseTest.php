<?php

declare(strict_types=1);

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Security\Hash;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function recuperacion_armar(): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();
    $solicitar   = new SolicitarRecuperacionUseCase(
        usuarioRepo:        $usuarioRepo,
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             new CorreoAuthService($mailer, 'https://app.test'),
        recuperacionTtlMin: 60
    );
    $restablecer = new RestablecerPasswordUseCase($usuarioRepo, $tokenRepo);
    return [$solicitar, $restablecer, $usuarioRepo, $tokenRepo, $mailer];
}

test('Recuperar: email existente y activo genera token tipo recuperacion + 1 correo', function (): void {
    [$solicitar, , $usuarioRepo, $tokenRepo, $mailer] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');

    $solicitar->execute('ana@test.local');

    assert_same(1, count($tokenRepo->tokens));
    assert_same(AuthToken::TIPO_RECUPERACION, array_values($tokenRepo->tokens)[0]->tipo());
    assert_same(1, count($mailer->enviados));
});

test('Recuperar: email inexistente o inactivo — mismo resultado externo y cero correos', function (): void {
    [$solicitar, , $usuarioRepo, $tokenRepo, $mailer] = recuperacion_armar();
    $inactiva = fake_usuario(2, 'inactiva@test.local')->desactivar();
    $usuarioRepo->usuarios[2] = $inactiva;

    $solicitar->execute('nadie@test.local');
    $solicitar->execute('inactiva@test.local');
    $solicitar->execute('no-es-email');

    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Recuperar: la 4ª solicitud en la hora no envía correo (throttle silencioso)', function (): void {
    [$solicitar, , $usuarioRepo, , $mailer] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');

    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');
    $solicitar->execute('ana@test.local');

    assert_same(3, count($mailer->enviados), 'solo 3 correos: la 4ª se throttlea sin error');
});

test('Restablecer: actualiza el hash y consume el token', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    $restablecer->execute('tok-rec', 'nuevoPass123', 'nuevoPass123');

    assert_true($usuarioRepo->ultimoUpdate !== null);
    assert_true(Hash::verify('nuevoPass123', $usuarioRepo->ultimoUpdate->passwordHash()), 'hash nuevo verificable');
    assert_null($tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-rec'), AuthToken::TIPO_RECUPERACION), 'token consumido');
});

test('Restablecer: token consumido no se reutiliza', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    $restablecer->execute('tok-rec', 'nuevoPass123', 'nuevoPass123');

    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'otroPass123', 'otroPass123'));
});

test('Restablecer: password inválido o sin coincidencia lanza ValidationException sin consumir token', function (): void {
    [, $restablecer, $usuarioRepo, $tokenRepo] = recuperacion_armar();
    $usuarioRepo->usuarios[1] = fake_usuario(1, 'ana@test.local');
    $tokenRepo->guardar(AuthToken::desdeFila([
        'usuario_id' => 1,
        'tipo'       => AuthToken::TIPO_RECUPERACION,
        'token_hash' => hash('sha256', 'tok-rec'),
        'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
    ]));

    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'corto', 'corto'));
    assert_throws(ValidationException::class, fn() => $restablecer->execute('tok-rec', 'nuevoPass123', 'distinto123'));

    assert_true(
        $tokenRepo->buscarVigentePorHash(hash('sha256', 'tok-rec'), AuthToken::TIPO_RECUPERACION) !== null,
        'el token sigue vigente tras fallos de validación'
    );
});
