<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

final class InvoiceSecretScrubber
{
    /** @var list<string> */
    private const META_DENYLIST = [
        'secret',
        'token',
        'password',
        'api_key',
        'authorization',
        'webhook_secret',
    ];

    public static function sanitizeSecretTokens(string $message): string
    {
        $sanitized = preg_replace('/sk_(test|live|user)_[A-Za-z0-9]+/', '[redacted]', $message);
        if (! is_string($sanitized)) {
            $sanitized = $message;
        }

        $sanitized = preg_replace('/Bearer\s+\S+/i', '[redacted]', $sanitized);

        return is_string($sanitized) ? $sanitized : $message;
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public static function scrubMetaSecrets(array $meta): array
    {
        $scrubbed = [];

        foreach ($meta as $key => $value) {
            if (self::isDeniedMetaKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $value = self::scrubNestedMetaLevel($value);
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private static function scrubNestedMetaLevel(array $meta): array
    {
        $scrubbed = [];

        foreach ($meta as $key => $value) {
            if (self::isDeniedMetaKey((string) $key)) {
                continue;
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }

    private static function isDeniedMetaKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::META_DENYLIST as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
