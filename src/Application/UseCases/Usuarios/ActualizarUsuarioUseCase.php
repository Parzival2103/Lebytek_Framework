<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Usuarios;

use Lebytek\Framework\Application\DTO\Usuarios\ActualizarUsuarioDTO;
use Lebytek\Framework\Application\Validators\Usuarios\CrearUsuarioValidator;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\ValueObjects\Email;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Security\Hash;

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

        $actualizado = new \Lebytek\Framework\Domain\Entities\Usuario(
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
