<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Roles;

use Lebytek\Framework\Application\DTO\Roles\CrearRolDTO;
use Lebytek\Framework\Domain\Entities\Rol;
use Lebytek\Framework\Domain\ValueObjects\Slug;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

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
