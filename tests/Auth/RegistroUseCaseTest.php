<?php

declare(strict_types=1);

use Lebytek\Framework\Application\DTO\Auth\RegistroDTO;
use Lebytek\Framework\Application\Services\AuthTokenService;
use Lebytek\Framework\Application\Services\CorreoAuthService;
use Lebytek\Framework\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use Lebytek\Framework\Application\Validators\Usuarios\CrearUsuarioValidator;
use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/auth_fakes.php';

function registro_armar(array $overrides = []): array
{
    $usuarioRepo = new FakeUsuarioRepository();
    $rolRepo     = (new FakeRolRepository())->conRol('usuario', 7, 'Usuario');
    $tokenRepo   = new FakeAuthTokenRepository();
    $mailer      = new FakeMailer();

    $useCase = new RegistrarUsuarioUseCase(
        usuarioRepo:        $usuarioRepo,
        rolRepo:            $rolRepo,
        validator:          new CrearUsuarioValidator(),
        tokens:             new AuthTokenService($tokenRepo, 3),
        correo:             fake_correo_auth_service($mailer),
        habilitado:         $overrides['habilitado'] ?? true,
        rolDefault:         'usuario',
        verificacionTtlMin: 1440
    );

    return [$useCase, $usuarioRepo, $rolRepo, $tokenRepo, $mailer];
}

function registro_dto(array $overrides = []): RegistroDTO
{
    return new RegistroDTO(
        nombre:               $overrides['nombre'] ?? 'Ana',
        apellido:             $overrides['apellido'] ?? 'Lopez',
        email:                $overrides['email'] ?? 'ana@test.local',
        password:             $overrides['password'] ?? 'secreto123',
        passwordConfirmacion: $overrides['passwordConfirmacion'] ?? 'secreto123'
    );
}

test('Registro: crea usuario inactivo con rol default, 1 token de verificación y 1 correo', function (): void {
    [$useCase, $usuarioRepo, $rolRepo, $tokenRepo, $mailer] = registro_armar();

    $useCase->execute(registro_dto());

    assert_same(1, count($usuarioRepo->usuarios));
    $usuario = array_values($usuarioRepo->usuarios)[0];
    assert_true(!$usuario->activo(), 'el registrado nace inactivo hasta verificar');
    assert_same('ana@test.local', (string) $usuario->email());

    assert_same([[1, 7]], $rolRepo->asignaciones, 'rol default asignado al id nuevo');

    assert_same(1, count($tokenRepo->tokens));
    assert_same(AuthToken::TIPO_VERIFICACION, array_values($tokenRepo->tokens)[0]->tipo());

    assert_same(1, count($mailer->enviados));
    assert_same('ana@test.local', $mailer->enviados[0]->destinatario);
});

test('Registro: email duplicado lanza ValidationException y no envía nada', function (): void {
    [$useCase, $usuarioRepo, , $tokenRepo, $mailer] = registro_armar();
    $usuarioRepo->emailsExistentes[] = 'ana@test.local';

    assert_throws(ValidationException::class, fn() => $useCase->execute(registro_dto()));
    assert_same(0, count($tokenRepo->tokens));
    assert_same(0, count($mailer->enviados));
});

test('Registro: deshabilitado lanza ValidationException', function (): void {
    [$useCase] = registro_armar(['habilitado' => false]);

    assert_throws(ValidationException::class, fn() => $useCase->execute(registro_dto()));
});

test('Registro: confirmación de contraseña distinta lanza ValidationException', function (): void {
    [$useCase, $usuarioRepo] = registro_armar();

    assert_throws(ValidationException::class, fn() => $useCase->execute(
        registro_dto(['passwordConfirmacion' => 'otra-cosa'])
    ));
    assert_same(0, count($usuarioRepo->usuarios));
});

test('Registro: password corto lanza ValidationException (reglas de CrearUsuarioValidator)', function (): void {
    [$useCase] = registro_armar();

    assert_throws(ValidationException::class, fn() => $useCase->execute(
        registro_dto(['password' => 'corto', 'passwordConfirmacion' => 'corto'])
    ));
});
