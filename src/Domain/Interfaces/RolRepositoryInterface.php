<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Domain\Entities\Rol;

interface RolRepositoryInterface
{
    public function findById(int $id): ?Rol;

    public function findBySlug(string $slug): ?Rol;

    /** @return Rol[] */
    public function findAll(): array;

    public function save(Rol $rol): int;

    public function update(Rol $rol): void;

    public function delete(int $id): void;

    /** @return Rol[] */
    public function buscarPorUsuarioId(int $usuarioId): array;

    public function asignarRolAUsuario(int $usuarioId, int $rolId): void;

    public function revocarRolDeUsuario(int $usuarioId, int $rolId): void;

    public function sincronizarRolesDeUsuario(int $usuarioId, array $rolIds): void;
}
