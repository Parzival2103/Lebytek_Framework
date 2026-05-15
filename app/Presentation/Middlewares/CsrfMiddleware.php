<?php

declare(strict_types=1);

namespace App\Presentation\Middlewares;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

/*
|--------------------------------------------------------------------------
| CsrfMiddleware — Valida token CSRF en métodos mutantes
|--------------------------------------------------------------------------
*/

final class CsrfMiddleware
{
    private static array $excludedPaths = ['/api/'];

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        foreach (self::$excludedPaths as $path) {
            if (str_starts_with($request->uri(), $path)) {
                return $next($request);
            }
        }

        $token = $request->input('_csrf_token', '')
              ?: $request->header('X-CSRF-Token', '');

        if (!Csrf::verify((string) $token)) {
            Session::flash('error', 'La sesión expiró o el token es inválido. Intenta de nuevo.');
            return Response::redirect($request->header('Referer', '/'));
        }

        return $next($request);
    }
}
