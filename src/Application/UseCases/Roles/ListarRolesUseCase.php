<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Roles;

use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;

final class ListarRolesUseCase
{
    public function __construct(
        private readonly RolRepositoryInterface     $rolRepo,
        private readonly PermisoRepositoryInterface $permisoRepo
    ) {}

    public function execute(): array
    {
        $roles = $this->rolRepo->findAll();

        return array_map(fn($rol) => $rol->toArray(), $roles);
    }

    public function obtenerPorId(int $id): ?array
    {
        $rol = $this->rolRepo->findById($id);

        return $rol?->toArray();
    }

    /**
     * Grupos para formularios de rol: datos 100 % desde auth_permisos,
     * agrupación por columna modulo o, si vacía, por prefijo del slug antes del primer punto.
     *
     * @return list<array{grupo_id: string, grupo_label: string, permisos: list<array<string, mixed>>}>
     */
    public function obtenerPermisosAgrupadosParaFormulario(): array
    {
        $permisos = $this->permisoRepo->findAllActivosOrdenadosPorModuloSlug();
        $buckets  = [];

        foreach ($permisos as $permiso) {
            $row  = $permiso->toArray();
            $slug = (string) ($row['slug'] ?? '');
            $moduloDb = trim((string) ($row['modulo'] ?? ''));

            if ($moduloDb !== '') {
                $grupoKey = strtolower(preg_replace('/[^a-z0-9_]/', '_', $moduloDb));
            } else {
                $grupoKey = $this->grupoInferidoDesdeSlug($slug);
            }
            if ($grupoKey === '') {
                $grupoKey = 'general';
            }

            if (! isset($buckets[$grupoKey])) {
                $buckets[$grupoKey] = [
                    'grupo_id'    => $grupoKey,
                    'grupo_label' => $this->etiquetaGrupo($grupoKey),
                    'permisos'    => [],
                ];
            }
            $buckets[$grupoKey]['permisos'][] = $row;
        }

        foreach ($buckets as &$b) {
            usort(
                $b['permisos'],
                static fn(array $a, array $z): int => strcmp((string) $a['slug'], (string) $z['slug'])
            );
        }
        unset($b);

        uasort(
            $buckets,
            static fn(array $a, array $b): int => strcmp($a['grupo_label'], $b['grupo_label'])
        );

        return array_values($buckets);
    }

    public function obtenerPermisosAsignados(int $rolId): array
    {
        $asignados = $this->permisoRepo->buscarPorRolId($rolId);

        return array_map(fn($permiso) => $permiso->id(), $asignados);
    }

    private function grupoInferidoDesdeSlug(string $slug): string
    {
        $parts = explode('.', $slug, 2);
        $head  = $parts[0] ?? '';

        return strtolower(preg_replace('/[^a-z0-9_]/', '_', $head));
    }

    private function etiquetaGrupo(string $grupoKey): string
    {
        return mb_convert_case(str_replace('_', ' ', $grupoKey), MB_CASE_TITLE, 'UTF-8');
    }
}
