<?php

declare(strict_types=1);

namespace App\Application\UseCases\Usuarios;

use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\Exceptions\ValidationException;

final class EliminarUsuarioUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo
    ) {}

    public function execute(int $id, ?int $usuarioActualId = null): void
    {
        if ($usuarioActualId !== null && $usuarioActualId === $id) {
            throw new ValidationException('No puedes eliminar tu propio usuario.');
        }

        $usuario = $this->usuarioRepo->findById($id);

        if ($usuario === null) {
            throw new ValidationException('El usuario no existe.');
        }

        $this->usuarioRepo->delete($id);
    }
}
