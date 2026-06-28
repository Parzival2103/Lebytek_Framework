<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Auth;

use Lebytek\Framework\Application\DTO\Auth\RegistroDTO;
use Lebytek\Framework\Application\Services\AuthTokenService;
use Lebytek\Framework\Application\Services\CorreoAuthService;
use Lebytek\Framework\Application\Validators\Usuarios\CrearUsuarioValidator;
use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Domain\ValueObjects\Email;
use Lebytek\Framework\Kernel\Security\Hash;

/*
|--------------------------------------------------------------------------
| RegistrarUsuarioUseCase — Registro público con verificación de correo
|--------------------------------------------------------------------------
| Crea el usuario inactivo con el rol default, emite token de verificación
| y envía el correo. El usuario no puede iniciar sesión hasta verificar.
*/

final class RegistrarUsuarioUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly CrearUsuarioValidator      $validator,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly bool                       $habilitado,
        private readonly string                     $rolDefault,
        private readonly int                        $verificacionTtlMin
    ) {
    }

    public function execute(RegistroDTO $dto): void
    {
        if (!$this->habilitado) {
            throw new ValidationException('El registro no está disponible.');
        }

        $this->validator->validate([
            'nombre'   => $dto->nombre,
            'apellido' => $dto->apellido,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        if ($dto->password !== $dto->passwordConfirmacion) {
            throw new ValidationException('Los datos del registro son inválidos.', [
                'password_confirmacion' => 'Las contraseñas no coinciden.',
            ]);
        }

        $email = new Email($dto->email);

        if ($this->usuarioRepo->emailExists($email)) {
            throw new ValidationException('El correo ya está registrado.', [
                'email' => 'Este correo ya existe.',
            ]);
        }

        $usuario = new Usuario(
            nombre:       $dto->nombre,
            apellido:     $dto->apellido,
            email:        $email,
            passwordHash: Hash::make($dto->password),
            activo:       false
        );

        $id = $this->usuarioRepo->save($usuario);

        $rol = $this->rolRepo->findBySlug($this->rolDefault);
        if ($rol !== null && $rol->id() !== null) {
            $this->rolRepo->asignarRolAUsuario($id, $rol->id());
        }

        $token = $this->tokens->emitir($id, AuthToken::TIPO_VERIFICACION, $this->verificacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarVerificacion($usuario, $token);
        }
    }
}
