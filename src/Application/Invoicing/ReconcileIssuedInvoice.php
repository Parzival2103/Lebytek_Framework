<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousCreate;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceExternalIdCollision;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderIdConflict;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;
use Throwable;

/**
 * A15/A27: verifies remote state before promoting `needs_reconcile` rows, and
 * recovers claimed-without-id orphans via `listByExternalId` (A22/A24) before any
 * manual intervention. Never calls `createInvoice` from `handle()` (A22).
 */
final class ReconcileIssuedInvoice
{
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_ISSUED = 'issued';
    private const STATUS_NEEDS_RECONCILE = 'needs_reconcile';
    private const STATUS_CANCELED = 'canceled';

    public function __construct(
        private readonly InvoiceEventLogRepositoryInterface $events,
        private readonly InvoiceProviderRegistry $registry,
        private readonly ?string $defaultProviderKey = null,
        private readonly ?InvoiceableSourceInterface $source = null,
        private readonly ?InvoiceDraftValidator $validator = null,
    ) {
    }

    public function handle(string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
    {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $provider = $this->registry->get($resolvedProviderKey);
        $row = $this->requireClaimRow($resolvedProviderKey, $idempotencyKey);

        return match ($row->ledgerStatus()) {
            self::STATUS_ISSUED, self::STATUS_CANCELED => $this->reload($resolvedProviderKey, $idempotencyKey),
            self::STATUS_NEEDS_RECONCILE => $row->providerInvoiceId() !== null
                ? $this->reconcileRemote($resolvedProviderKey, $idempotencyKey, $row, $provider)
                : $this->reconcileOrphan($resolvedProviderKey, $idempotencyKey, $row, $provider),
            self::STATUS_CLAIMED => $this->reconcileOrphan($resolvedProviderKey, $idempotencyKey, $row, $provider),
            default => throw new RuntimeException(sprintf(
                'Invoice idempotency key "%s" for provider "%s" has unexpected ledger status "%s".',
                $idempotencyKey,
                $resolvedProviderKey,
                $row->ledgerStatus(),
            )),
        };
    }

    /**
     * A26, ops-only. Never invoked by `handle()`. Reuses the SAME idempotency key
     * (and therefore the SAME `external_id`, A23) so the remote `idempotency_key`
     * covers the race; safe only because `listByExternalId` proves zero remote hits.
     */
    public function forceReissueOrphanClaim(string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
    {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $provider = $this->registry->get($resolvedProviderKey);
        $row = $this->requireClaimRow($resolvedProviderKey, $idempotencyKey);

        if ($row->ledgerStatus() !== self::STATUS_CLAIMED || $row->providerInvoiceId() !== null) {
            throw new RuntimeException(sprintf(
                'forceReissueOrphanClaim only applies to claimed rows without a provider invoice id (idempotency key "%s", provider "%s").',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        if ($this->claimAgeSeconds($row) < $this->minClaimAgeSeconds()) {
            throw new RuntimeException(sprintf(
                'forceReissueOrphanClaim requires claim age >= reconcile_min_claim_age_seconds (idempotency key "%s", provider "%s").',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        $externalId = $this->resolveExternalId($row, $provider, $idempotencyKey);
        $matches = $provider->listByExternalId($externalId);
        if (count($matches) > 0) {
            throw new RuntimeException(sprintf(
                'forceReissueOrphanClaim requires zero remote matches for external_id "%s" (idempotency key "%s", provider "%s").',
                $externalId,
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        if ($this->source === null || $this->validator === null) {
            throw new RuntimeException(
                'forceReissueOrphanClaim requires an InvoiceableSourceInterface and InvoiceDraftValidator to rebuild the draft.',
            );
        }

        $sourceRef = trim((string) $row->sourceRef());
        if ($sourceRef === '') {
            throw new RuntimeException(sprintf(
                'forceReissueOrphanClaim cannot rebuild a draft without a source_ref (idempotency key "%s", provider "%s").',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        $draft = $this->source->findDraft($sourceRef);
        if ($draft === null) {
            throw new InvoiceSourceNotFound(sprintf('Invoice source "%s" was not found.', $sourceRef));
        }
        $this->validator->validate($draft);

        $observedInvoice = $provider->createInvoice($draft, $idempotencyKey);
        try {
            $this->events->markIssued($resolvedProviderKey, $idempotencyKey, $observedInvoice);
        } catch (Throwable $e) {
            try {
                $this->events->markNeedsReconcile($resolvedProviderKey, $idempotencyKey, $observedInvoice);
            } catch (Throwable) {
                try {
                    $this->events->attachProviderInvoiceId(
                        $resolvedProviderKey,
                        $idempotencyKey,
                        $observedInvoice->providerInvoiceId(),
                        ['external_id' => $externalId],
                    );
                } catch (Throwable) {
                    // Last-resort attach failed; keep the claim and surface typed reconciliation data.
                }
            }

            throw new InvoiceNeedsReconcile(
                sprintf(
                    'Invoice "%s" was force-reissued remotely but local issue mark failed.',
                    $observedInvoice->providerInvoiceId(),
                ),
                $observedInvoice->providerInvoiceId(),
                $resolvedProviderKey,
                $idempotencyKey,
                previous: $e,
            );
        }

        return $this->reload($resolvedProviderKey, $idempotencyKey);
    }

    /** @return IssuedInvoice[] */
    public function listNeedsReconcile(?string $providerKey = null, int $limit = 100): array
    {
        return $this->events->findNeedsReconcile($this->resolveProviderKey($providerKey), $limit);
    }

    /** @return InvoiceClaimRow[] */
    public function listOrphanClaims(?string $providerKey = null, int $limit = 100): array
    {
        return $this->events->findOrphanClaims(
            $this->resolveProviderKey($providerKey),
            $this->minClaimAgeSeconds(),
            $limit,
        );
    }

    private function reconcileRemote(
        string $providerKey,
        string $idempotencyKey,
        InvoiceClaimRow $row,
        InvoiceProviderInterface $provider,
    ): IssuedInvoice {
        $remote = $provider->retrieveInvoice((string) $row->providerInvoiceId());

        return $this->promoteFromRemote($providerKey, $idempotencyKey, $remote);
    }

    private function reconcileOrphan(
        string $providerKey,
        string $idempotencyKey,
        InvoiceClaimRow $row,
        InvoiceProviderInterface $provider,
    ): IssuedInvoice {
        if ($this->claimAgeSeconds($row) < $this->minClaimAgeSeconds()) {
            throw new InvoiceAmbiguousCreate(
                $providerKey,
                $idempotencyKey,
                (string) $row->sourceRef(),
                reason: 'claim too fresh; may be an issue still in flight',
            );
        }

        $externalId = $this->resolveExternalId($row, $provider, $idempotencyKey);
        $matches = $provider->listByExternalId($externalId);
        $matchCount = count($matches);

        if ($matchCount === 0) {
            throw new InvoiceAmbiguousCreate(
                $providerKey,
                $idempotencyKey,
                (string) $row->sourceRef(),
                reason: 'listByExternalId found zero remote invoices; claim kept, use forceReissueOrphanClaim if appropriate',
            );
        }

        if ($matchCount > 1) {
            throw new InvoiceExternalIdCollision($providerKey, $idempotencyKey, $externalId, $matchCount);
        }

        $match = $matches[0];

        try {
            $this->events->attachProviderInvoiceId(
                $providerKey,
                $idempotencyKey,
                $match->providerInvoiceId(),
                ['external_id' => $externalId],
            );
        } catch (InvoiceProviderIdConflict) {
            // A27: lost the race against the issuing process; re-read and return as-is.
            return $this->reload($providerKey, $idempotencyKey);
        }

        $remote = $provider->retrieveInvoice($match->providerInvoiceId());

        return $this->promoteFromRemote($providerKey, $idempotencyKey, $remote);
    }

    private function promoteFromRemote(string $providerKey, string $idempotencyKey, IssuedInvoice $remote): IssuedInvoice
    {
        if ($remote->status() === InvoiceStatus::Canceled) {
            $this->events->markCanceled($providerKey, $idempotencyKey, $remote);
        } else {
            // A16/A27: pending/valid/unknown remote states promote the ledger row to
            // `issued` while `meta.provider_status` keeps the real fiscal status.
            $this->events->markIssued($providerKey, $idempotencyKey, $remote);
        }

        return $this->reload($providerKey, $idempotencyKey);
    }

    private function reload(string $providerKey, string $idempotencyKey): IssuedInvoice
    {
        $reloaded = $this->events->findByIdempotencyKey($providerKey, $idempotencyKey);
        if ($reloaded === null) {
            throw new RuntimeException(sprintf(
                'Invoice idempotency key "%s" for provider "%s" could not be reloaded after reconcile.',
                $idempotencyKey,
                $providerKey,
            ));
        }

        return $reloaded;
    }

    private function requireClaimRow(string $providerKey, string $idempotencyKey): InvoiceClaimRow
    {
        $row = $this->events->findClaimByIdempotencyKey($providerKey, $idempotencyKey);
        if ($row === null) {
            throw new InvoiceSourceNotFound(sprintf(
                'Invoice idempotency key "%s" was not found for provider "%s".',
                $idempotencyKey,
                $providerKey,
            ));
        }

        return $row;
    }

    private function resolveExternalId(InvoiceClaimRow $row, InvoiceProviderInterface $provider, string $idempotencyKey): string
    {
        $metaExternalId = $row->meta()['external_id'] ?? null;

        return is_string($metaExternalId) && $metaExternalId !== ''
            ? $metaExternalId
            : $provider->externalIdForIssue($idempotencyKey);
    }

    private function claimAgeSeconds(InvoiceClaimRow $row): int
    {
        $seconds = (new \DateTimeImmutable())->getTimestamp() - $row->createdAt()->getTimestamp();

        return max(0, $seconds);
    }

    private function minClaimAgeSeconds(): int
    {
        return (int) Config::get('invoicing.reconcile_min_claim_age_seconds', 120);
    }

    private function resolveProviderKey(?string $providerKey): string
    {
        $resolved = $providerKey ?? $this->defaultProviderKey ?? Config::get('invoicing.default', 'facturapi');
        $resolved = trim((string) $resolved);
        if ($resolved === '') {
            throw new RuntimeException('Default invoice provider is not configured.');
        }

        if (! $this->registry->has($resolved)) {
            throw new RuntimeException("Proveedor de facturación no registrado: {$resolved}");
        }

        return $resolved;
    }
}
