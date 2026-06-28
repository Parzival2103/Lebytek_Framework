<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\MenuCatalogRepositoryInterface;
use App\Domain\Policies\RbacPolicy;
use App\Kernel\Vertical\VerticalProfile;

/*
|--------------------------------------------------------------------------
| Menú administrativo ya filtrado por vertical del deploy y RBAC del usuario.
|--------------------------------------------------------------------------
| No usa Session — el controlador pasa los slugs de permisos y roles.
*/

final class AdminNavigationMenuService
{
    public function __construct(
        private readonly MenuCatalogRepositoryInterface $menuCatalogRepository
    ) {}

    /**
     * @param list<string> $permisosUsuario
     * @param list<string> $rolesUsuario
     *
     * @return list<array<string, mixed>>
     */
    public function menuFiltradoParaUsuario(array $permisosUsuario, array $rolesUsuario): array
    {
        $menuItems = $this->menuCatalogRepository->obtenerArbolParaVista();
        $menuItems = VerticalProfile::filterMenuByModules($menuItems);
        $menuItems = VerticalProfile::applyMenuLabels($menuItems);

        $policy = new RbacPolicy($permisosUsuario, $rolesUsuario);

        $filtrados = [];
        foreach ($menuItems as $item) {
            $parentPermitted = empty($item['permiso']) || $policy->puede((string) $item['permiso']);

            if (! empty($item['submenu'])) {
                $subFiltrados = [];
                foreach ($item['submenu'] as $sub) {
                    if (empty($sub['permiso']) || $policy->puede((string) $sub['permiso'])) {
                        $subFiltrados[] = $sub;
                    }
                }
                $item['submenu'] = $subFiltrados;

                if ($item['submenu'] === []) {
                    continue;
                }

                // Si el padre no tiene permiso directo, pero hay hijos visibles, conservar el padre como agrupador.
                if (! $parentPermitted) {
                    $item['url'] = '';
                }

                $filtrados[] = $item;
                continue;
            }

            if (! $parentPermitted) {
                continue;
            }

            $filtrados[] = $item;
        }

        return $filtrados;
    }
}
