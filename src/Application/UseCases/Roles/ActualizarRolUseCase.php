<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Roles;

use Lebytek\Framework\Application\DTO\Roles\ActualizarRolDTO;
use Lebytek\Framework\Domain\Entities\Rol;
use Lebytek\Framework\Domain\ValueObjects\Slug;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

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
