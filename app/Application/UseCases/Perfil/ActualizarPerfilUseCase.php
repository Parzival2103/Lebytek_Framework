<?php

declare(strict_types=1);

namespace App\Application\UseCases\Perfil;

use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;

/*
|--------------------------------------------------------------------------
| ActualizarPerfilUseCase — Edición de los datos del propio usuario
|--------------------------------------------------------------------------
| Solo nombre/apellido/email. No toca password, activo, roles ni avatar
| (a diferencia de ActualizarUsuarioUseCase, que exige roles/activo y
| resincroniza — incorrecto para el perfil propio).
*/

final class ActualizarPerfilUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly CrearUsuarioValidator $validator
    ) {
    }

    /** @param array{nombre?: string, apellido?: string, email?: string} $datos */
    public function execute(int $usuarioId, array $datos): void
    {
        $this->validator->validateUpdate([
            'nombre'   => $datos['nombre'] ?? '',
            'apellido' => $datos['apellido'] ?? '',
            'email'    => $datos['email'] ?? '',
            'password' => null,
        ]);

        $usuario = $this->usuarioRepo->findById($usuarioId);
        if ($usuario === null) {
            throw new ValidationException('El usuario no existe.');
        }

        $email = new Email((string) $datos['email']);
        if ($this->usuarioRepo->emailExists($email, $usuarioId)) {
            throw new ValidationException('El correo ya está en uso.', ['email' => 'Este correo ya existe.']);
        }

        $actualizado = new Usuario(
            nombre:       (string) $datos['nombre'],
            apellido:     (string) $datos['apellido'],
            email:        $email,
            passwordHash: $usuario->passwordHash(),
            activo:       $usuario->activo(),
            avatar:       $usuario->avatar(),
            ultimoAcceso: $usuario->ultimoAcceso(),
            creadoEn:     $usuario->creadoEn(),
            id:           $usuarioId
        );

        $this->usuarioRepo->update($actualizado);
    }
}
