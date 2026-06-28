<?php

declare(strict_types=1);

namespace App\Application\UseCases\Roles;

use App\Application\DTO\Roles\CrearRolDTO;
use App\Domain\Entities\Rol;
use App\Domain\ValueObjects\Slug;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\Exceptions\ValidationException;

final class CrearRolUseCase
{
    public function __construct(
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly PermisoRepositoryInterface $permisoRepo
    ) {}

    public function execute(CrearRolDTO $dto): int
    {
        if (trim($dto->nombre) === '') {
            throw new ValidationException(
                'El nombre del rol es obligatorio.',
                ['nombre' => 'El nombre es obligatorio.']
            );
        }

        $slug = !empty($dto->slug)
            ? new Slug(trim($dto->slug))
            : Slug::fromString($dto->nombre);

        $rol = new Rol(
            nombre:      trim($dto->nombre),
            slug:        $slug,
            descripcion: trim($dto->descripcion),
            activo:      $dto->activo
        );

        $id = $this->rolRepo->save($rol);

        $permisoIds = array_map('intval', $dto->permisoIds);
        $this->permisoRepo->sincronizarPermisosDeRol($id, $permisoIds);

        return $id;
    }
}
