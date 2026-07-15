<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

/**
 * Rate limiter para el colector público `/marketing/collect` (Anti-deuda §L).
 *
 * **Nunca** basado en `Session` PHP: `navigator.sendBeacon` viaja a menudo sin
 * cookie de sesión. La implementación de infraestructura usa `sys_kv`.
 */
interface CollectRateLimiterInterface
{
    /**
     * @param string $key Clave de ventana, p. ej. `land_collect:{visitor_id}`.
     * @return bool `true` si la request está permitida (no excede el límite).
     */
    public function allow(string $key): bool;
}
