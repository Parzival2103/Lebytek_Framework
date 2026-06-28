<?php

declare(strict_types=1);

namespace App\Application\UseCases\Roles;

use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Exceptions\ValidationException;

final class EliminarRolUseCase
{
    public function __construct(
        private readonly RolRepositoryInterface $rolRepo
    ) {}

    public function execute(int $id): void
    {
        $rol = $this->rolRepo->findById($id);

        if ($rol === null) {
            throw new ValidationException('El rol no existe.');
        }

        $this->rolRepo->delete($id);
    }
}
