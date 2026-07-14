<?php

declare(strict_types=1);

namespace App\Domain\Marketing;

/** Crockford Base32 ULID (26 chars). */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(?int $timeMs = null): string
    {
        $timeMs ??= (int) floor(microtime(true) * 1000);
        $time = '';
        $t = $timeMs;
        for ($i = 0; $i < 10; $i++) {
            $time = self::ALPHABET[$t % 32].$time;
            $t = intdiv($t, 32);
        }

        $random = '';
        $bytes = random_bytes(10);
        for ($i = 0; $i < 16; $i++) {
            $random .= self::ALPHABET[ord($bytes[$i % 10]) % 32];
        }

        return $time.$random;
    }
}
