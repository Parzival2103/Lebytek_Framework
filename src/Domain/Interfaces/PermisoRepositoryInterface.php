<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Entities\Permiso;

interface PermisoRepositoryInterface
{
    public function findById(int $id): ?Permiso;

    public function findBySlug(string $slug): ?Permiso;

    /** @return Permiso[] */
    public function findAll(): array;

    /**
     * Catálogo asignable a roles: solo vigentes en BD (`activo = 1`).
     *
     * @return Permiso[]
     */
    public function findAllActivosOrdenadosPorModuloSlug(): array;

    /** @return Permiso[] */
    public function buscarPorRolId(int $rolId): array;

    /** @return string[] Slugs de permisos del usuario */
    public function slugsPorUsuarioId(int $usuarioId): array;

    /**
     * Filtra a IDs que existen en auth_permisos (defensa ante POST manipulado).
     *
     * @param list<int|string> $permisoIds
     * @return list<int>
     */
    public function filterExistingPermisoIds(array $permisoIds, bool $soloActivos = false): array;

    /** @return list<string> slugs únicos en BD */
    public function listarTodosLosSlugs(): array;

    /**
     * Mapa slug → flag activo (0 o 1), para informes y clasificación vigente/deprecado.
     *
     * @return array<string, int>
     */
    public function mapSlugActivo(): array;

    public function sincronizarPermisosDeRol(int $rolId, array $permisoIds): void;

    public function save(Permiso $permiso): int;

    public function update(Permiso $permiso): void;

    public function delete(int $id): void;
}
