<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

/*
|--------------------------------------------------------------------------
| Catálogo de ítems de menú (core) — sólo datos; sin RBAC ni vertical.
|--------------------------------------------------------------------------
*/

interface MenuCatalogRepositoryInterface
{
    /**
     * Devuelve el árbol en el formato histórico de vista (padres con `submenu`).
     *
     * @return list<array<string, mixed>>
     */
    public function obtenerArbolParaVista(): array;

    /**
     * Slugs de permiso referenciados por ítems de menú activos (permiso_slug no vacío).
     *
     * @return list<string>
     */
    public function listarSlugsPermisoReferenciadosEnMenu(): array;
}
