<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface IntegrationLogRepositoryInterface
{
    /** @param array<string, mixed> $meta */
    public function record(
        string $channel,
        string $driver,
        string $recipientMasked,
        string $status,
        ?string $providerMessageId,
        ?string $error,
        array $meta
    ): void;

    /** Número de envíos del canal en los últimos $windowSeconds (para rate-limit). */
    public function countRecent(string $channel, int $windowSeconds): int;

    /**
     * Últimos envíos para el visor (más recientes primero).
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50, ?string $channel = null): array;
}
