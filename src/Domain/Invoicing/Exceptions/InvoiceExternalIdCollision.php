<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\Exceptions;

use RuntimeException;

/**
 * A22/A23 orphan recovery found more than one remote invoice for the same
 * per-attempt `external_id`. Under A23 the `external_id` -> invoice relationship
 * is 1:1 (remote `idempotency_key` guarantees it), so this is real corruption.
 * Fail closed: never guess which provider invoice id belongs to the claim.
 */
final class InvoiceExternalIdCollision extends RuntimeException
{
    public function __construct(
        private readonly string $providerKey,
        private readonly string $idempotencyKey,
        private readonly string $externalId,
        private readonly int $matchCount,
    ) {
        parent::__construct(sprintf(
            'Orphan recovery for provider "%s", idempotency key "%s" found %d invoices for external_id "%s"; refusing to pick one.',
            $providerKey,
            $idempotencyKey,
            $matchCount,
            $externalId,
        ));
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function matchCount(): int
    {
        return $this->matchCount;
    }
}
