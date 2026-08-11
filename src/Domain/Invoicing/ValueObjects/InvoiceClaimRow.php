<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

use DateTimeImmutable;

/**
 * A24 read model over `inv_events` with no `provider_invoice_id IS NOT NULL` filter,
 * so orphan claims (claimed without an observed provider id) remain visible.
 */
final readonly class InvoiceClaimRow
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        private string $provider,
        private string $idempotencyKey,
        private ?string $sourceRef,
        private string $type,
        private string $ledgerStatus,
        private ?string $providerInvoiceId,
        private array $meta,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function sourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** claimed | issued | needs_reconcile | canceled */
    public function ledgerStatus(): string
    {
        return $this->ledgerStatus;
    }

    public function providerInvoiceId(): ?string
    {
        return $this->providerInvoiceId;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
