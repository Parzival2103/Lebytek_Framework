<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\EnvLoader;

final class SignedToken
{
    public static function make(int $accountId, int $ttlSeconds = 86400): string
    {
        $exp = time() + $ttlSeconds;
        $payload = $accountId . '.' . $exp;
        $sig = self::sign($payload);
        return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
    }

    public static function verify(string $token): ?int
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            return null;
        }
        [$accountId, $exp, $sig] = $parts;
        if (!hash_equals(self::sign($accountId . '.' . $exp), $sig)) {
            return null;
        }
        if ((int) $exp < time()) {
            return null;
        }
        return (int) $accountId;
    }

    private static function sign(string $payload): string
    {
        $key = (string) EnvLoader::get('APP_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('SignedToken: APP_KEY ausente.');
        }
        return hash_hmac('sha256', $payload, $key);
    }
}
