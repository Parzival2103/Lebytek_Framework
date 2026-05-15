<?php

declare(strict_types=1);

namespace App\Application\UseCases\Usuarios;

use App\Application\DTO\Usuarios\CrearUsuarioDTO;
use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Domain\Entities\Usuario;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Security\Hash;

final class CrearUsuarioUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly CrearUsuarioValidator      $validator
    ) {}

    public function execute(CrearUsuarioDTO $dto): int
    {
        $this->validator->validate([
            'nombre'   => $dto->nombre,
            'apellido' => $dto->apellido,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        $email = new Email($dto->email);

        if ($this->usuarioRepo->emailExists($email)) {
            throw new ValidationException('El correo ya está registrado.', ['email' => 'Este correo ya existe.']);
        }

        $usuario = new Usuario(
            nombre:       $dto->nombre,
            apellido:     $dto->apellido,
            email:        $email,
            passwordHash: Hash::make($dto->password),
            activo:       $dto->activo
        );

        $id = $this->usuarioRepo->save($usuario);

        if (!empty($dto->rolIds)) {
            $this->rolRepo->sincronizarRolesDeUsuario($id, $dto->rolIds);
        }

        return $id;
    }
}
