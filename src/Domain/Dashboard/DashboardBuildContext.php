<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Dashboard;

/**
 * Datos del usuario ya autenticado filtrados para el dominio (sin dependencias de Session en capas inferiores al controlador).
 *
 * @param list<string> $permisoSlugs Slugs efectivos desde auth_permisos
 * @param list<string> $rolSlugs     Slugs de roles desde auth_roles
 */
final class DashboardBuildContext
{
    /**
     * PHP 8.2+ permite `readonly class`; en 8.1 usar propiedades promoted `readonly` (compatibilidad hosting).
     *
     * @param list<string> $permisoSlugs
     * @param list<string> $rolSlugs
     */
    public function __construct(
        public readonly ?int $usuarioId,
        public readonly array $permisoSlugs,
        public readonly array $rolSlugs,
    ) {}

    public function tienePermiso(string $slug): bool
    {
        return in_array($slug, $this->permisoSlugs, true);
    }

    public function tieneRol(string $slug): bool
    {
        return in_array($slug, $this->rolSlugs, true);
    }
}
