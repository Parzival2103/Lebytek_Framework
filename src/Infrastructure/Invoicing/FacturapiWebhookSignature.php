<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;

final class FacturapiWebhookSignature
{
    public static function assertValid(string $rawBody, string $signatureHeader, string $webhookSecret): void
    {
        $secret = trim($webhookSecret);
        $provided = self::normalizeSignature($signatureHeader);

        if ($secret === '' || $provided === null) {
            throw new InvoiceProviderException('Facturapi webhook signature is invalid.');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expected, strtolower($provided))) {
            throw new InvoiceProviderException('Facturapi webhook signature is invalid.');
        }
    }

    private static function normalizeSignature(string $signatureHeader): ?string
    {
        $signature = trim($signatureHeader);
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, strlen('sha256='));
        }

        $signature = trim($signature);
        if (! preg_match('/\A[a-fA-F0-9]{64}\z/', $signature)) {
            return null;
        }

        return $signature;
    }
}
