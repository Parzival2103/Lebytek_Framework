<?php

declare(strict_types=1);

use Lebytek\Framework\Application\UseCases\Perfil\ActualizarPerfilUseCase;
use Lebytek\Framework\Application\Validators\Usuarios\CrearUsuarioValidator;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

require_once __DIR__ . '/../fixtures/avatar_fakes.php';

function perfil_use_case(FakeUsuarioRepository $repo): ActualizarPerfilUseCase
{
    return new ActualizarPerfilUseCase($repo, new CrearUsuarioValidator());
}

test('ActualizarPerfilUseCase actualiza nombre/apellido/email del propio usuario', function (): void {
    $repo = new FakeUsuarioRepository();
    $repo->usuarios[3] = fake_usuario(3, 'ana@test.local');

    perfil_use_case($repo)->execute(3, ['nombre' => 'Anabel', 'apellido' => 'Lugo', 'email' => 'anabel@test.local']);

    $actualizado = $repo->ultimoUpdate;
    assert_true($actualizado !== null, 'debe llamar a update');
    assert_same(3, $actualizado->id());
    assert_same('Anabel', $actualizado->nombre());
    assert_same('Lugo', $actualizado->apellido());
    assert_same('anabel@test.local', (string) $actualizado->email());
});

test('ActualizarPerfilUseCase preserva password, activo y avatar', function (): void {
    $repo = new FakeUsuarioRepository();
    $repo->usuarios[3] = new \Lebytek\Framework\Domain\Entities\Usuario(
        nombre: 'Ana', apellido: 'Lopez',
        email: new \Lebytek\Framework\Domain\ValueObjects\Email('ana@test.local'),
        passwordHash: 'hash-original',
        activo: false,
        avatar: '/uploads/avatars/actual.png',
        id: 3
    );

    perfil_use_case($repo)->execute(3, ['nombre' => 'Ana', 'apellido' => 'Lopez', 'email' => 'ana@test.local']);

    $actualizado = $repo->ultimoUpdate;
    assert_same('hash-original', $actualizado->passwordHash(), 'password intacto');
    assert_same(false, $actualizado->activo(), 'activo intacto');
    assert_same('/uploads/avatars/actual.png', $actualizado->avatar(), 'avatar intacto');
});

test('ActualizarPerfilUseCase rechaza email duplicado de otro usuario', function (): void {
    $repo = new FakeUsuarioRepository();
    $repo->usuarios[3] = fake_usuario(3, 'ana@test.local');
    $repo->emailsExistentes = ['otro@test.local'];

    assert_throws(ValidationException::class, function () use ($repo): void {
        perfil_use_case($repo)->execute(3, ['nombre' => 'Ana', 'apellido' => 'Lopez', 'email' => 'otro@test.local']);
    });
    assert_null($repo->ultimoUpdate, 'no debe actualizar');
});

test('ActualizarPerfilUseCase valida campos obligatorios', function (): void {
    $repo = new FakeUsuarioRepository();
    $repo->usuarios[3] = fake_usuario(3);

    assert_throws(ValidationException::class, function () use ($repo): void {
        perfil_use_case($repo)->execute(3, ['nombre' => '', 'apellido' => 'Lopez', 'email' => 'ana@test.local']);
    });
    assert_throws(ValidationException::class, function () use ($repo): void {
        perfil_use_case($repo)->execute(3, ['nombre' => 'Ana', 'apellido' => 'Lopez', 'email' => 'no-es-email']);
    });
});

test('ActualizarPerfilUseCase rechaza usuario inexistente', function (): void {
    $repo = new FakeUsuarioRepository();

    assert_throws(ValidationException::class, function () use ($repo): void {
        perfil_use_case($repo)->execute(99, ['nombre' => 'Ana', 'apellido' => 'Lopez', 'email' => 'ana@test.local']);
    });
});
