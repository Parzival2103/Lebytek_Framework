<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Http\Request;

/**
 * Maps CRUD/calendario request URI + HTTP verb to `{permission_prefix}.{action}`.
 *
 * Uses CalendarConfigLoader::crudDefinition() for JSON-only permission_prefix reads
 * (no DB). Full CrudConfigLoader validation remains in the CRUD service layer.
 */
final class CrudRoutePermissionResolver
{
    public function __construct(
        private readonly CalendarConfigLoader $calendarConfigLoader,
    ) {}

    public function resolve(Request $request): string
    {
        $uri = $request->uri();

        if (preg_match('#^/admin/calendario/([^/]+)(?:/eventos)?$#', $uri, $m)) {
            $key = (string) ($request->param('key') ?: $m[1]);
            $def = $this->calendarConfigLoader->load($key);
            $crud = $this->calendarConfigLoader->crudDefinition($def->resource());

            return $crud->permissionPrefix() . '.ver';
        }

        if (preg_match('#^/admin/crud/([^/]+)#', $uri, $m)) {
            $resource = (string) ($request->param('resource') ?: $m[1]);
            $definition = $this->calendarConfigLoader->crudDefinition($resource);
            $action = $this->resolveCrudAction($request, $uri);

            return $definition->permissionFor($action);
        }

        throw new ValidationException('Ruta CRUD/calendario no reconocida para RBAC.');
    }

    private function resolveCrudAction(Request $request, string $uri): string
    {
        if (preg_match('#/eliminar$#', $uri)) {
            return 'eliminar';
        }
        if (preg_match('#/accion-masiva/#', $uri) || preg_match('#/accion/#', $uri)) {
            return 'ver';
        }
        if (preg_match('#/crear$#', $uri)) {
            return 'crear';
        }
        if (preg_match('#/editar$#', $uri)) {
            return 'editar';
        }
        if ($request->isPost()) {
            if (preg_match('#^/admin/crud/[^/]+$#', $uri)) {
                return 'crear';
            }

            return 'editar';
        }
        if (preg_match('#^/admin/crud/[^/]+/[^/]+$#', $uri) && !str_contains($uri, '/crear')) {
            return 'ver';
        }

        return 'ver';
    }
}
