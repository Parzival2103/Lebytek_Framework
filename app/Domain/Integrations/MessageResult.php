<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/*
|--------------------------------------------------------------------------
| MessageResult — resultado uniforme de un envío (nunca una excepción).
|--------------------------------------------------------------------------
*/
final class MessageResult
{
    /** @param array<string, mixed> $rawResponse */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
        public readonly array $rawResponse = []
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function sent(string $providerMessageId, array $raw = []): self
    {
        return new self(true, $providerMessageId, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failed(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }
}
