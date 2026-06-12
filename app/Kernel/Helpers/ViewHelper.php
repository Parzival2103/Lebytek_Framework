<?php

declare(strict_types=1);

namespace App\Kernel\Helpers;

use App\Kernel\Security\Session;
use App\Kernel\Security\Csrf;
use App\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| ViewHelper — Renderizador de vistas PHP con soporte de layouts
|--------------------------------------------------------------------------
| Renderiza archivos .php de /app/Presentation/Views/ con datos extraídos
| como variables locales. Soporta layout wrapping con secciones.
*/

final class ViewHelper
{
    private static array $sections    = [];
    private static ?string $currentSection = null;

    /**
     * Renderiza una vista y la envuelve en su layout si se especificó.
     *
     * @param string $view   Ruta relativa a Views/ sin .php (ej: "admin/dashboard")
     * @param array  $data   Variables a pasar a la vista
     * @param string $layout Layout a usar (por defecto "layouts/base")
     */
    public static function render(string $view, array $data = [], string $layout = 'layouts/base'): string
    {
        $viewFile = APP_PATH . '/Presentation/Views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$viewFile}");
        }

        // Renderizar contenido de la vista
        $content = self::renderFile($viewFile, $data);

        if ($layout === '') {
            return $content;
        }

        // Renderizar layout inyectando el contenido
        $layoutFile = APP_PATH . '/Presentation/Views/' . $layout . '.php';

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout no encontrado: {$layoutFile}");
        }

        return self::renderFile($layoutFile, array_merge($data, ['content' => $content]));
    }

    /**
     * Renderiza un archivo PHP con los datos dados como variables locales.
     */
    public static function renderFile(string $filePath, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $filePath;
        return ob_get_clean();
    }

    /**
     * Renderiza un partial de /Presentation/Views/partials/
     */
    public static function partial(string $name, array $data = []): string
    {
        return self::renderFile(
            APP_PATH . '/Presentation/Views/partials/' . $name . '.php',
            $data
        );
    }

    // ── Helpers de seguridad para templates ──────────────────────────────────

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function csrfField(): string
    {
        return Csrf::field();
    }

    public static function csrfToken(): string
    {
        return Csrf::token();
    }

    /**
     * Genera atributos data-confirm-* para el modal de confirmación global (#confirmModal).
     * Claves: body, title, ok, cancel, variant, cancelVariant, icon, emphasis (todas opcionales; se omiten si están vacías).
     * Variantes válidas: primary|secondary|success|danger|warning|info|dark.
     * Iconos válidos: warning|danger|success|info|question.
     */
    public static function confirmAttrs(array $opts): string
    {
        $map = [
            'body'          => 'data-confirm',
            'title'         => 'data-confirm-title',
            'ok'            => 'data-confirm-ok',
            'cancel'        => 'data-confirm-cancel',
            'variant'       => 'data-confirm-variant',
            'cancelVariant' => 'data-confirm-cancel-variant',
            'icon'          => 'data-confirm-icon',
            'emphasis'      => 'data-confirm-emphasis',
        ];

        $attrs = [];
        foreach ($map as $key => $attr) {
            $value = (string) ($opts[$key] ?? '');
            if ($value !== '') {
                $attrs[] = $attr . '="' . self::e($value) . '"';
            }
        }

        return implode(' ', $attrs);
    }

    public static function old(string $key, mixed $default = ''): string
    {
        return self::e(Session::oldInput($key, $default));
    }

    public static function flash(string $key): mixed
    {
        return Session::getFlash($key);
    }

    public static function hasFlash(string $key): bool
    {
        return Session::hasFlash($key);
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    // ── Helpers de color ─────────────────────────────────────────────────────

    public static function colorAjustar(string $hex, int $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#' . $hex;
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
        return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
    }

    public static function colorRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '13, 110, 253';
        return implode(', ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }

    public static function calcularLuminancia(string $hex): float
    {
        $h = ltrim($hex, '#');
        if (strlen($h) !== 6) return 0.0;
        $toLinear = static fn(float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $r = $toLinear(hexdec(substr($h, 0, 2)) / 255);
        $g = $toLinear(hexdec(substr($h, 2, 2)) / 255);
        $b = $toLinear(hexdec(substr($h, 4, 2)) / 255);
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function calcularPrimarySubtle(string $primaryColor): string
    {
        $ph = ltrim($primaryColor, '#');
        if (strlen($ph) !== 6) return '#e8f0fe';
        return '#' . sprintf('%02x%02x%02x',
            (int) (hexdec(substr($ph, 0, 2)) * 0.12 + 255 * 0.88),
            (int) (hexdec(substr($ph, 2, 2)) * 0.12 + 255 * 0.88),
            (int) (hexdec(substr($ph, 4, 2)) * 0.12 + 255 * 0.88)
        );
    }

    public static function navbarTextColors(string $navbarColor): array
    {
        $lum = self::calcularLuminancia($navbarColor);
        if ($lum > 0.35) {
            return [
                'text'      => 'rgba(30,30,30,0.9)',
                'muted'     => 'rgba(30,30,30,0.5)',
                'separator' => 'rgba(0,0,0,0.1)',
            ];
        }
        return [
            'text'      => 'rgba(255,255,255,0.88)',
            'muted'     => 'rgba(255,255,255,0.45)',
            'separator' => 'rgba(255,255,255,0.1)',
        ];
    }

    // ── Helpers de URL ────────────────────────────────────────────────────────

    public static function url(string $path = ''): string
    {
        $base = rtrim(Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Prefijo de ruta del front controller (p. ej. /public) cuando la app no está en la raíz del host.
     */
    public static function basePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        return rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }

    public static function asset(string $path): string
    {
        return self::url('assets/' . ltrim($path, '/'));
    }
}
