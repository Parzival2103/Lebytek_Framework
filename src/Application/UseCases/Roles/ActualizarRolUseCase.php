<?php

declare(strict_types=1);

namespace App\Application\UseCases\Roles;

use App\Application\DTO\Roles\ActualizarRolDTO;
use App\Domain\Entities\Rol;
use App\Domain\ValueObjects\Slug;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\Exceptions\ValidationException;

final class ActualizarRolUseCase
{
    public function __construct(
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly PermisoRepositoryInterface $permisoRepo
    ) {}

    public function execute(ActualizarRolDTO $dto): void
    {
        if (trim($dto->nombre) === '') {
            throw new ValidationException(
                'El nombre del rol es obligatorio.',
                ['nombre' => 'El nombre es obligatorio.']
            );
        }

        $existente = $this->rolRepo->findById($dto->id);

        if ($existente === null) {
            throw new ValidationException('El rol no existe.');
        }

        $rol = new Rol(
            nombre:      trim($dto->nombre),
            slug:        new Slug(trim($dto->slug)),
            descripcion: trim($dto->descripcion),
            activo:      $dto->activo,
            id:          $dto->id
        );

        $this->rolRepo->update($rol);

        $permisoIds = array_map('intval', $dto->permisoIds);
        $this->permisoRepo->sincronizarPermisosDeRol($dto->id, $permisoIds);
    }
}
