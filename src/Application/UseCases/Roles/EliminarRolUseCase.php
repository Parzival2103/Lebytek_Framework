<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Roles;

use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

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
