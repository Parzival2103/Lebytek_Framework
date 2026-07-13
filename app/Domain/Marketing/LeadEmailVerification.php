<?php
declare(strict_types=1);

namespace App\Domain\Marketing;

final class LeadEmailVerification
{
    public const TTL_HOURS = 24;
    public const MAX_ATTEMPTS = 5;
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generateCode(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashCode(string $plain): string
    {
        return hash('sha256', strtoupper(trim($plain)));
    }

    public static function codeMatches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hashCode($plain));
    }

    public static function expiresAtFromNow(): string
    {
        return (new \DateTimeImmutable('now'))
            ->modify('+' . self::TTL_HOURS . ' hours')
            ->format('Y-m-d H:i:s');
    }
}
