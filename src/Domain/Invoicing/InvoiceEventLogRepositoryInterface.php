<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

interface InvoiceEventLogRepositoryInterface
{
    /**
     * True when a row exists with provider_invoice_id (issued OR needs_reconcile).
     */
    public function hasProcessed(string $provider, string $idempotencyKey): bool;

    /**
     * Atomic claim: INSERT UNIQUE(provider, idempotency_key).
     *
     * @return true if this caller owns the claim; false if already claimed.
     * @param array<string, mixed> $meta
     */
    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool;

    public function releaseClaim(string $provider, string $idempotencyKey): void;

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void;

    public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void;

    public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void;

    /**
     * @param array<string, mixed> $meta
     */
    public function attachProviderInvoiceId(
        string $provider,
        string $idempotencyKey,
        string $providerInvoiceId,
        array $meta = [],
    ): void;

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice;

    /** @return IssuedInvoice[] */
    public function findIssuedBySourceRef(string $sourceRef): array;

    /** @return IssuedInvoice[] */
    public function findNeedsReconcile(string $provider, int $limit = 100): array;

    /**
     * A24 read model lookup: returns the claim row regardless of whether a
     * provider invoice id was ever observed (no `provider_invoice_id IS NOT NULL` filter).
     * Required for orphan recovery (A22); `findByIdempotencyKey` cannot see orphans.
     */
    public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?InvoiceClaimRow;

    /**
     * A24: resolves the claim row that issued a given provider invoice id, so callers
     * (Task 6 cancel flow) can recover the idempotency key from a provider-observed id.
     */
    public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?InvoiceClaimRow;

    /**
     * A24 ops sweep: claims left `claimed` with no observed provider invoice id, older
     * than `$minAgeSeconds`. `findNeedsReconcile` filters `provider_invoice_id IS NOT NULL`
     * and therefore cannot see these rows.
     *
     * @return InvoiceClaimRow[]
     */
    public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array;
}
