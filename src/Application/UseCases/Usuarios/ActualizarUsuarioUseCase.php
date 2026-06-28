<?php

declare(strict_types=1);

namespace App\Application\UseCases\Usuarios;

use App\Application\DTO\Usuarios\ActualizarUsuarioDTO;
use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Security\Hash;

final class ActualizarUsuarioUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly CrearUsuarioValidator      $validator
    ) {}

    public function execute(ActualizarUsuarioDTO $dto): void
    {
        $this->validator->validateUpdate([
            'nombre'   => $dto->nombre,
            'apellido' => $dto->apellido,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        $email   = new Email($dto->email);
        $usuario = $this->usuarioRepo->findById($dto->id);

        if ($usuario === null) {
            throw new ValidationException('El usuario no existe.');
        }

        if ($this->usuarioRepo->emailExists($email, $dto->id)) {
            throw new ValidationException('El correo ya está en uso.', ['email' => 'Este correo ya existe.']);
        }

        $hash = !empty($dto->password)
            ? Hash::make($dto->password)
            : $usuario->passwordHash();

        $actualizado = new \App\Domain\Entities\Usuario(
            nombre:       $dto->nombre,
            apellido:     $dto->apellido,
            email:        $email,
            passwordHash: $hash,
            activo:       $dto->activo,
            avatar:       $usuario->avatar(),
            ultimoAcceso: $usuario->ultimoAcceso(),
            creadoEn:     $usuario->creadoEn(),
            id:           $dto->id
        );

        $this->usuarioRepo->update($actualizado);
        $this->rolRepo->sincronizarRolesDeUsuario($dto->id, $dto->rolIds);
    }
}
