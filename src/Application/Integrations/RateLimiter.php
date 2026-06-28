<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Integrations;

use Lebytek\Framework\Domain\Integrations\IntegrationLogRepositoryInterface;

/*
|--------------------------------------------------------------------------
| RateLimiter — límite básico por canal (envíos por ventana).
|--------------------------------------------------------------------------
| Reusa int_logs como contador (vía countRecent), evitando infraestructura
| nueva. Patrón de límite por ventana análogo a LoginRateLimitService.
*/
final class RateLimiter
{
    /** @param array<string, array{max:int, window_seconds:int}> $limits */
    public function __construct(
        private readonly array $limits,
        private readonly IntegrationLogRepositoryInterface $logs
    ) {
    }

    public function allow(string $channel): bool
    {
        $cfg = $this->limits[$channel] ?? null;
        if ($cfg === null) {
            return true;
        }

        $max = (int) ($cfg['max'] ?? 0);
        if ($max <= 0) {
            return true;
        }

        $window = (int) ($cfg['window_seconds'] ?? 60);
        return $this->logs->countRecent($channel, $window) < $max;
    }
}
