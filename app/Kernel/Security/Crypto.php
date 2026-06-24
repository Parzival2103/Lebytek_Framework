<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\EnvLoader;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        $iv = random_bytes($ivLen);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Crypto: fallo al cifrar.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $key = self::key();
        $raw = base64_decode($payload, true);
        $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        if ($raw === false || strlen($raw) < $ivLen + 16) {
            throw new \RuntimeException('Crypto: payload inválido.');
        }
        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, 16);
        $cipher = substr($raw, $ivLen + 16);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Crypto: fallo al descifrar.');
        }
        return $plain;
    }

    private static function key(): string
    {
        $appKey = (string) EnvLoader::get('APP_KEY', '');
        if ($appKey === '') {
            throw new \RuntimeException('Crypto: APP_KEY ausente en .env.');
        }
        return hash('sha256', $appKey, true);
    }
}
