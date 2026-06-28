<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| Hash — Hashing seguro de contraseñas con bcrypt
|--------------------------------------------------------------------------
*/

final class Hash
{
    public static function make(string $value): string
    {
        $rounds = (int) Config::get('security.bcrypt_rounds', 12);

        return password_hash($value, PASSWORD_BCRYPT, ['cost' => $rounds]);
    }

    public static function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $rounds = (int) Config::get('security.bcrypt_rounds', 12);
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $rounds]);
    }
}
