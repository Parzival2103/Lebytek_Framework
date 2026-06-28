<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Security;

use Lebytek\Framework\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| Csrf — Protección contra Cross-Site Request Forgery
|--------------------------------------------------------------------------
| Genera tokens únicos por sesión y los valida en requests mutantes
| (POST, PUT, PATCH, DELETE).
*/

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::has(self::SESSION_KEY)) {
            $length = (int) Config::get('security.csrf_token_length', 32);
            Session::set(self::SESSION_KEY, bin2hex(random_bytes($length)));
        }

        return (string) Session::get(self::SESSION_KEY);
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function metaTag(): string
    {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function verify(string $token): bool
    {
        $stored = Session::get(self::SESSION_KEY, '');
        return hash_equals($stored, $token);
    }

    public static function regenerate(): void
    {
        $length = (int) Config::get('security.csrf_token_length', 32);
        Session::set(self::SESSION_KEY, bin2hex(random_bytes($length)));
    }
}
