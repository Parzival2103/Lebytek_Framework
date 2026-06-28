<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\BaseClasses;

use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Http\SafeRedirect;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Security\Csrf;

abstract class BaseController
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/base'): Response
    {
        $html = ViewHelper::render($view, $data, $layout);
        return Response::html($html);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function back(Request $request): Response
    {
        $referer = SafeRedirect::toInternal($request->header('Referer', '/'));
        return Response::redirect($referer);
    }

    protected function redirectWithFlash(string $url, string $type, string $message): Response
    {
        Session::flash($type, $message);
        return Response::redirect($url);
    }

    protected function verifyCsrf(Request $request): void
    {
        $token = $request->input('_csrf_token', '')
              ?: $request->header('X-CSRF-Token', '');

        if (!Csrf::verify((string) $token)) {
            Session::flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
            throw new \Lebytek\Framework\Kernel\Exceptions\HttpException('CSRF token inválido.', 419);
        }
    }

    protected function currentUser(): ?array
    {
        return Session::get('auth_user');
    }

    protected function isAuthenticated(): bool
    {
        return Session::has('auth_user');
    }
}
