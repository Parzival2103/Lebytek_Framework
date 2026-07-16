<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use Lebytek\Framework\Kernel\EnvLoader;

final class StripePriceResolver
{
    public static function resolve(string $planSlug, string $ciclo): string
    {
        $cycleKey = $ciclo === 'annual' ? 'ANNUAL' : 'MONTHLY';
        $envKey = 'STRIPE_PRICE_'.strtoupper($planSlug).'_'.$cycleKey;
        $priceId = trim((string) EnvLoader::get($envKey, ''));
        if ($priceId === '') {
            throw new \InvalidArgumentException("Stripe price id not configured ({$envKey}).");
        }

        return $priceId;
    }
}
