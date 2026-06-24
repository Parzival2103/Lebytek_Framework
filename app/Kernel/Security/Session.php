<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| Session — Gestión segura de sesiones PHP
|--------------------------------------------------------------------------
| Configura sesiones con parámetros de seguridad, regeneración de ID
| y helpers para flash messages.
*/

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = (int) Config::get('session.lifetime', 120) * 60;
        $secure   = (bool) Config::get('session.secure', false);

        session_name('CONTRASTE_SESSION');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;

        // Regenerar ID si es una sesión nueva para prevenir session fixation
        if (!isset($_SESSION['_initiated'])) {
            session_regenerate_id(true);
            $_SESSION['_initiated'] = true;
        }
    }

    // ── Operaciones básicas ───────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        return $_SESSION;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Extiende la cookie de sesión y gc_maxlifetime (p. ej. checkbox "Recordarme").
     * Llamar tras login exitoso, antes de persistir datos de auth en $_SESSION.
     */
    public static function aplicarDuracionRecordar(): void
    {
        if (!self::$started || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $minutes = (int) Config::get('session.remember_lifetime', 43200);
        $seconds = max(60, $minutes * 60);

        ini_set('session.gc_maxlifetime', (string) $seconds);

        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $seconds,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    // ── Flash messages ────────────────────────────────────────────────────────

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    public static function flashAll(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public static function flashInput(array $input): void
    {
        $_SESSION['_old_input'] = $input;
        unset($_SESSION['_old_input_age']);
    }

    public static function oldInput(string $key, mixed $default = ''): mixed
    {
        $value = $_SESSION['_old_input'][$key] ?? $default;
        return $value;
    }

    public static function clearOldInput(): void
    {
        unset($_SESSION['_old_input']);
    }

    /**
     * Envejece el old input para que solo sobreviva un ciclo de request.
     * Llamar al inicio de cada request en Bootstrap.
     */
    public static function ageOldInput(): void
    {
        if (isset($_SESSION['_old_input_age'])) {
            unset($_SESSION['_old_input'], $_SESSION['_old_input_age']);
        } elseif (isset($_SESSION['_old_input'])) {
            $_SESSION['_old_input_age'] = true;
        }
    }
}
