<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

final class FacturapiExternalId
{
    private const PREFIX = 'lebytek:invoice:';

    private function __construct()
    {
    }

    public static function forIssueClaim(string $providerKey, string $idempotencyKey): string
    {
        return self::PREFIX . substr(hash('sha256', $providerKey . "\x1f" . $idempotencyKey), 0, 40);
    }
}
