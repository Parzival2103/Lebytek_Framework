<?php

declare(strict_types=1);

namespace App\Presentation\Middlewares;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| AuthMiddleware — Verifica que el usuario tenga sesión activa
|--------------------------------------------------------------------------
*/

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Session::has('auth_user')) {
            Session::flash('info', 'Debes iniciar sesión para continuar.');
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
