#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Smoke manual: POST /messages con token por-tenant.
 * Uso: php scripts/smoke-send-test-message.php <tenantToken> <instancePublicId> <recipientE164> <body>
 */

require dirname(__DIR__).'/vendor/autoload.php';

use Lebytek\Framework\Kernel\EnvLoader;

if ($argc < 5) {
    fwrite(STDERR, "Usage: php scripts/smoke-send-test-message.php <tenantToken> <instancePublicId> <recipientE164> <body>\n");
    exit(1);
}

[, $token, $instancePublicId, $recipient, $body] = $argv;

define('ROOT_PATH', dirname(__DIR__));
EnvLoader::load(ROOT_PATH.'/.env');

$baseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
$idempotencyKey = bin2hex(random_bytes(16));

$ch = curl_init($baseUrl.'/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json',
        'Accept: application/json',
        'Idempotency-Key: '.$idempotencyKey,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'recipient' => $recipient,
        'body' => $body,
        'instancePublicId' => $instancePublicId,
    ], JSON_THROW_ON_ERROR),
]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

fwrite(STDOUT, "HTTP {$status}\n{$raw}\n");

exit($status === 202 ? 0 : 1);
