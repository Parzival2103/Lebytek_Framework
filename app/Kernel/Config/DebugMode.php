<?php

declare(strict_types=1);

namespace App\Kernel\Config;

/**
 * Decide si el modo debug está activo. En producción siempre es false, sin
 * importar la configuración, para no exponer stack traces. En cualquier otro
 * entorno respeta el flag configurado (comportamiento sin cambios).
 */
final class DebugMode
{
    public static function resolve(?string $env, bool $configDebug): bool
    {
        if (strtolower((string) $env) === 'production') {
            return false;
        }
        return $configDebug;
    }
}
