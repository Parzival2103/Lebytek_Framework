<?php

declare(strict_types=1);

use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\AuthService;
use App\Application\Services\LoginRateLimitService;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\Validators\Auth\LoginValidator;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\AuthException;
use App\Domain\ValueObjects\Email;
use App\Kernel\Security\Hash;

require_once __DIR__ . '/../fixtures/auth_fakes.php';
require_once __DIR__ . '/../fixtures/avatar_fakes.php';

function login_use_case_con_usuario(
    FakeUsuarioRepository $usuarioRepo,
    FakeLoginIntentoRepository $intentoRepo,
    int $maxIntentos = 5
): LoginUseCase {
    $authService = new AuthService(
        $usuarioRepo,
        new FakePermisoRepository(),
        new FakeRolRepository()
    );
    $rateLimit = new LoginRateLimitService($intentoRepo, $maxIntentos, 15);

    return new LoginUseCase($authService, new LoginValidator(), $rateLimit);
}

function usuario_activo_con_password(FakeUsuarioRepository $repo, string $email, string $passwordPlano): void
{
    $usuario = new Usuario(
        nombre: 'Admin',
        apellido: 'Test',
        email: new Email($email),
        passwordHash: Hash::make($passwordPlano),
        activo: true,
        id: 1
    );
    $repo->usuarios[1] = $usuario;
}

test('LoginUseCase: bloqueado por rate limit no actualiza último acceso del usuario', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');

    for ($i = 0; $i < 3; $i++) {
        $intentoRepo->registrarFallo('10.0.0.1', 'admin@test.local');
    }

    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo, maxIntentos: 3);

    $lanzo = false;
    try {
        $useCase->execute(new LoginDTO(
            email: 'admin@test.local',
            password: 'secret123',
            recordar: false,
            clientIp: '10.0.0.1'
        ));
    } catch (AuthException $e) {
        $lanzo = true;
        assert_same('Credenciales incorrectas.', $e->getMessage());
    }

    assert_true($lanzo);
    assert_null($usuarioRepo->ultimoUpdate, 'autenticar no debe ejecutarse si ya está bloqueado');
});

test('LoginUseCase: credenciales incorrectas registran fallo en el repositorio', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');
    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo);

    $lanzo = false;
    try {
        $useCase->execute(new LoginDTO('admin@test.local', 'mala-password', false, '1.2.3.4'));
    } catch (AuthException $e) {
        $lanzo = true;
    }

    assert_true($lanzo);
    assert_same(2, count($intentoRepo->filas), 'un fallo = fila ip + fila email');
});

test('LoginUseCase: login exitoso limpia contadores de ip y email', function (): void {
    $usuarioRepo = new FakeUsuarioRepository();
    $intentoRepo = new FakeLoginIntentoRepository();
    usuario_activo_con_password($usuarioRepo, 'admin@test.local', 'secret123');
    $intentoRepo->registrarFallo('5.6.7.8', 'admin@test.local');

    $useCase = login_use_case_con_usuario($usuarioRepo, $intentoRepo);
    $useCase->execute(new LoginDTO('admin@test.local', 'secret123', false, '5.6.7.8'));

    assert_same(0, count($intentoRepo->filas));
    assert_true($usuarioRepo->ultimoUpdate !== null, 'debe registrar último acceso');
});
