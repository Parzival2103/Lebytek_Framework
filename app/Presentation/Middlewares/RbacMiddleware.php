<?php

declare(strict_types=1);

namespace App\Presentation\Middlewares;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Domain\Policies\RbacPolicy;
use App\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| RbacMiddleware — Verifica permiso requerido por ruta
|--------------------------------------------------------------------------
| Uso: new RbacMiddleware('usuarios.ver')
*/

final class RbacMiddleware
{
    public function __construct(private readonly string $permiso) {}

    public function handle(Request $request, callable $next): Response
    {
        $policy = new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles',    [])
        );

        if (!$policy->puede($this->permiso)) {
            if ($request->isAjax()) {
                return Response::json(['error' => 'Acceso denegado.'], 403);
            }
            Session::flash('error', 'No tienes permiso para acceder a esta sección.');
            return Response::forbidden();
        }

        return $next($request);
    }
}
