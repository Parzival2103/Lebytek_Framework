<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

interface PaymentEventLogRepositoryInterface
{
    public function hasProcessed(string $provider, string $eventId): bool;

    /**
     * Atomic claim: INSERT UNIQUE(provider, event_id).
     * @return true if this caller owns the event; false if already claimed.
     * @param array<string, mixed> $meta
     */
    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool;

    /** Libera un claim fallido para permitir reintento del proveedor (issue #21 C4). */
    public function releaseClaim(string $provider, string $eventId): void;
}
