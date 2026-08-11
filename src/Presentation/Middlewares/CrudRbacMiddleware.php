<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Middlewares;

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CrudRoutePermissionResolver;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Policies\RbacPolicy;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

final class CrudRbacMiddleware
{
    private readonly CrudRoutePermissionResolver $resolver;

    public function __construct(?CrudRoutePermissionResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new CrudRoutePermissionResolver(
            new CalendarConfigLoader(new CalendarConfigValidator())
        );
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            $permiso = $this->resolver->resolve($request);
        } catch (ValidationException) {
            return $next($request);
        }

        $policy = new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles', [])
        );

        if (!$policy->puede($permiso)) {
            if ($request->isAjax()) {
                return Response::json([
                    'error'   => 'Acceso denegado.',
                    'permiso' => $permiso,
                ], 403);
            }

            $message = "No tienes permiso para acceder a esta sección (`{$permiso}`). "
                . 'Solicítalo al administrador o revisa tu rol en Usuarios/Roles.';
            Session::flash('error', $message);

            // Include slug in body for actionable HTML 403 (U1/U6); flash alone is empty without renderer.
            return new Response($message, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $next($request);
    }
}
