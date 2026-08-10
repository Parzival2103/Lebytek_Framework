<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Invoicing\InvoiceSecretScrubber;
use Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository;
use Lebytek\Framework\Infrastructure\Invoicing\PdoOrganizationSettingsRepository;

/** @param class-string $class */
function invoicing_invoke_encode_meta(string $class, array $meta): ?string
{
    $reflection = new ReflectionClass($class);
    $method = $reflection->getMethod('encodeMeta');
    $method->setAccessible(true);
    $repository = $reflection->newInstanceWithoutConstructor();

    return $method->invoke($repository, $meta);
}

/** @return array<string, mixed> */
function invoicing_decode_meta(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    assert_true(is_array($decoded), 'meta json must decode to array');

    return $decoded;
}

test('InvoiceSecretScrubber elimina claves meta con subcadenas secretas', function (): void {
    $scrubbed = InvoiceSecretScrubber::scrubMetaSecrets([
        'provider_status' => 'valid',
        'api_key' => 'sk_live_leak',
        'client_secret' => 'top-level-secret',
        'auth_token' => 'jwt-value',
        'user_password' => 'plain-text',
        'authorization' => 'Bearer abc',
        'webhook_secret' => 'whsec_123',
        'external_id' => 'safe-external-id',
    ]);

    assert_same([
        'provider_status' => 'valid',
        'external_id' => 'safe-external-id',
    ], $scrubbed);
});

test('InvoiceSecretScrubber elimina claves secretas en arrays anidados un nivel', function (): void {
    $scrubbed = InvoiceSecretScrubber::scrubMetaSecrets([
        'context' => [
            'retry_count' => 1,
            'nested_token' => 'jwt-leak',
            'payload' => [
                'deep_api_key' => 'should-not-be-scrubbed-at-depth-2',
            ],
        ],
        'safe' => 'ok',
    ]);

    assert_same([
        'context' => [
            'retry_count' => 1,
            'payload' => [
                'deep_api_key' => 'should-not-be-scrubbed-at-depth-2',
            ],
        ],
        'safe' => 'ok',
    ], $scrubbed);
});

test('InvoiceSecretScrubber redacta sk_user y Bearer en mensajes', function (): void {
    $message = 'Auth failed sk_user_ABC123 with header Bearer eyJhbGciOiJIUzI1NiJ9';
    $sanitized = InvoiceSecretScrubber::sanitizeSecretTokens($message);

    assert_true(! str_contains($sanitized, 'sk_user_ABC123'), 'sk_user token must be redacted');
    assert_true(! str_contains($sanitized, 'Bearer eyJhbGciOiJIUzI1NiJ9'), 'Bearer token must be redacted');
    assert_true(str_contains($sanitized, '[redacted]'), 'redaction marker expected');
});

test('PdoInvoiceEventLogRepository encodeMeta aplica denylist antes de json_encode', function (): void {
    $encoded = invoicing_invoke_encode_meta(PdoInvoiceEventLogRepository::class, [
        'provider_status' => 'pending',
        'client_secret' => 'must-not-persist',
    ]);

    assert_true(is_string($encoded), 'encodeMeta must return json string');
    $decoded = invoicing_decode_meta($encoded);
    assert_same(['provider_status' => 'pending'], $decoded);
    assert_true(! str_contains($encoded, 'must-not-persist'), 'secret value must not appear in encoded meta');
});

test('PdoOrganizationSettingsRepository encodeMeta aplica denylist antes de json_encode', function (): void {
    $encoded = invoicing_invoke_encode_meta(PdoOrganizationSettingsRepository::class, [
        'label' => 'primary',
        'webhook_secret' => 'whsec_leak',
    ]);

    assert_true(is_string($encoded), 'encodeMeta must return json string');
    $decoded = invoicing_decode_meta($encoded);
    assert_same(['label' => 'primary'], $decoded);
    assert_true(! str_contains($encoded, 'whsec_leak'), 'secret value must not appear in encoded meta');
});
