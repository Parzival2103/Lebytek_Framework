<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderIdConflict;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

class InMemoryInvoiceEventLog implements InvoiceEventLogRepositoryInterface
{
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_ISSUED = 'issued';
    private const STATUS_NEEDS_RECONCILE = 'needs_reconcile';
    private const STATUS_CANCELED = 'canceled';

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];
    private int $nextId = 1;

    public function hasProcessed(string $provider, string $idempotencyKey): bool
    {
        $row = $this->rows[$this->key($provider, $idempotencyKey)] ?? null;

        return is_array($row) && $row['providerInvoiceId'] !== null;
    }

    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool {
        $key = $this->key($provider, $idempotencyKey);
        if (isset($this->rows[$key])) {
            return false;
        }

        $this->rows[$key] = [
            'id' => $this->nextId++,
            'provider' => $provider,
            'idempotencyKey' => $idempotencyKey,
            'sourceRef' => $sourceRef !== '' ? $sourceRef : null,
            'type' => $type,
            'providerInvoiceId' => null,
            'uuid' => null,
            'folioNumber' => null,
            'status' => self::STATUS_CLAIMED,
            'meta' => $meta,
            'createdAt' => new DateTimeImmutable(),
        ];

        return true;
    }

    public function releaseClaim(string $provider, string $idempotencyKey): void
    {
        $key = $this->key($provider, $idempotencyKey);
        $row = $this->rows[$key] ?? null;
        if (! is_array($row)) {
            return;
        }

        if ($row['status'] === self::STATUS_CLAIMED && $row['providerInvoiceId'] === null) {
            unset($this->rows[$key]);
        }
    }

    public function markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        $this->mark($provider, $idempotencyKey, $invoice, self::STATUS_ISSUED);
    }

    public function markNeedsReconcile(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        $this->mark($provider, $idempotencyKey, $invoice, self::STATUS_NEEDS_RECONCILE);
    }

    public function markCanceled(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void
    {
        $this->mark($provider, $idempotencyKey, $invoice, self::STATUS_CANCELED);
    }

    public function attachProviderInvoiceId(
        string $provider,
        string $idempotencyKey,
        string $providerInvoiceId,
        array $meta = [],
    ): void {
        $key = $this->key($provider, $idempotencyKey);
        if (! isset($this->rows[$key])) {
            throw new RuntimeException(sprintf(
                'Cannot attach provider invoice id for provider "%s" and idempotency key "%s": claim row not found.',
                $provider,
                $idempotencyKey,
            ));
        }

        $currentProviderInvoiceId = $this->rows[$key]['providerInvoiceId'];
        if (
            $currentProviderInvoiceId !== null
            && (string) $currentProviderInvoiceId !== $providerInvoiceId
        ) {
            throw new InvoiceProviderIdConflict(
                sprintf(
                    'Cannot attach provider invoice id for provider "%s" and idempotency key "%s": existing provider_invoice_id "%s" differs from "%s".',
                    $provider,
                    $idempotencyKey,
                    (string) $currentProviderInvoiceId,
                    $providerInvoiceId,
                ),
                $provider,
                $idempotencyKey,
                (string) $currentProviderInvoiceId,
                $providerInvoiceId,
            );
        }

        $this->rows[$key]['providerInvoiceId'] = $providerInvoiceId;
        $this->rows[$key]['status'] = self::STATUS_NEEDS_RECONCILE;
        $this->rows[$key]['meta'] = $this->mergeMetaPreservingExternalId(
            is_array($this->rows[$key]['meta']) ? $this->rows[$key]['meta'] : [],
            $meta,
        );
    }

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice
    {
        $row = $this->rows[$this->key($provider, $idempotencyKey)] ?? null;
        if (! is_array($row) || $row['providerInvoiceId'] === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findIssuedBySourceRef(string $sourceRef): array
    {
        $matches = array_filter(
            $this->rows,
            static fn (array $row): bool => $row['sourceRef'] === $sourceRef && $row['providerInvoiceId'] !== null
        );
        usort($matches, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return array_map(fn (array $row): IssuedInvoice => $this->hydrate($row), $matches);
    }

    public function findNeedsReconcile(string $provider, int $limit = 100): array
    {
        if ($limit <= 0) {
            return [];
        }

        $matches = array_filter(
            $this->rows,
            static fn (array $row): bool => $row['provider'] === $provider
                && $row['status'] === self::STATUS_NEEDS_RECONCILE
                && $row['providerInvoiceId'] !== null
        );
        usort($matches, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return array_map(
            fn (array $row): IssuedInvoice => $this->hydrate($row),
            array_slice($matches, 0, $limit)
        );
    }

    public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?InvoiceClaimRow
    {
        $row = $this->rows[$this->key($provider, $idempotencyKey)] ?? null;

        return is_array($row) ? $this->hydrateClaimRow($row) : null;
    }

    public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?InvoiceClaimRow
    {
        foreach ($this->rows as $row) {
            if (
                $row['provider'] === $provider
                && $row['type'] !== 'cancel'
                && $row['providerInvoiceId'] !== null
                && (string) $row['providerInvoiceId'] === $providerInvoiceId
            ) {
                return $this->hydrateClaimRow($row);
            }
        }

        return null;
    }

    public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array
    {
        if ($limit <= 0) {
            return [];
        }

        $threshold = (new DateTimeImmutable())->modify('-'.max(0, $minAgeSeconds).' seconds');
        $matches = array_filter(
            $this->rows,
            static fn (array $row): bool => $row['provider'] === $provider
                && $row['status'] === self::STATUS_CLAIMED
                && $row['providerInvoiceId'] === null
                && $row['createdAt'] <= $threshold
        );
        usort($matches, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return array_map(
            fn (array $row): InvoiceClaimRow => $this->hydrateClaimRow($row),
            array_slice($matches, 0, $limit)
        );
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeMetaPreservingExternalId(array $existing, array $incoming): array
    {
        $merged = array_merge($existing, $incoming);
        if (! array_key_exists('external_id', $merged) && array_key_exists('external_id', $existing)) {
            $merged['external_id'] = $existing['external_id'];
        }
        if (! array_key_exists('provider_status', $merged) && array_key_exists('provider_status', $existing)) {
            $merged['provider_status'] = $existing['provider_status'];
        }

        return $merged;
    }

    private function mark(string $provider, string $idempotencyKey, IssuedInvoice $invoice, string $status): void
    {
        $key = $this->key($provider, $idempotencyKey);
        if (! isset($this->rows[$key])) {
            throw new RuntimeException(sprintf(
                'Cannot mark invoice event for provider "%s" and idempotency key "%s": claim row not found.',
                $provider,
                $idempotencyKey,
            ));
        }

        $currentProviderInvoiceId = $this->rows[$key]['providerInvoiceId'];
        if (
            $currentProviderInvoiceId !== null
            && (string) $currentProviderInvoiceId !== ''
            && (string) $currentProviderInvoiceId !== $invoice->providerInvoiceId()
        ) {
            throw new RuntimeException(sprintf(
                'Cannot mark invoice event for provider "%s" and idempotency key "%s": existing provider_invoice_id "%s" differs from "%s".',
                $provider,
                $idempotencyKey,
                (string) $currentProviderInvoiceId,
                $invoice->providerInvoiceId(),
            ));
        }

        $existingMeta = is_array($this->rows[$key]['meta']) ? $this->rows[$key]['meta'] : [];

        $this->rows[$key]['providerInvoiceId'] = $invoice->providerInvoiceId();
        $this->rows[$key]['uuid'] = $invoice->uuid();
        $this->rows[$key]['folioNumber'] = $invoice->folioNumber();
        $this->rows[$key]['status'] = $status;
        $this->rows[$key]['sourceRef'] = $invoice->sourceRef() ?? $this->rows[$key]['sourceRef'];
        $this->rows[$key]['meta'] = $this->mergeMetaPreservingExternalId($existingMeta, $this->metaForMark($invoice));
    }

    /** @return array<string, mixed> */
    private function metaForMark(IssuedInvoice $invoice): array
    {
        $meta = $invoice->meta();
        if (! array_key_exists('provider_status', $meta)) {
            $meta['provider_status'] = $invoice->status()->value;
        }

        return $meta;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): IssuedInvoice
    {
        $meta = is_array($row['meta']) ? $row['meta'] : [];

        return new IssuedInvoice(
            (string) $row['providerInvoiceId'],
            (string) $row['uuid'],
            $this->domainStatus((string) $row['status'], $meta),
            $row['folioNumber'] !== null ? (string) $row['folioNumber'] : null,
            $row['sourceRef'] !== null ? (string) $row['sourceRef'] : null,
            meta: $meta,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateClaimRow(array $row): InvoiceClaimRow
    {
        return new InvoiceClaimRow(
            (string) $row['provider'],
            (string) $row['idempotencyKey'],
            $row['sourceRef'] !== null ? (string) $row['sourceRef'] : null,
            (string) $row['type'],
            (string) $row['status'],
            $row['providerInvoiceId'] !== null ? (string) $row['providerInvoiceId'] : null,
            is_array($row['meta']) ? $row['meta'] : [],
            $row['createdAt'],
        );
    }

    /**
     * A16: ledger status `issued`/`needs_reconcile` must not coerce a provider
     * `pending`/`unknown` status into `Valid`; restore fidelity from `meta.provider_status`.
     *
     * @param array<string, mixed> $meta
     */
    private function domainStatus(string $status, array $meta): InvoiceStatus
    {
        if ($status === self::STATUS_ISSUED || $status === self::STATUS_NEEDS_RECONCILE) {
            $providerStatus = $meta['provider_status'] ?? null;
            if (is_string($providerStatus) && $providerStatus !== '') {
                return InvoiceStatus::fromProvider($providerStatus);
            }

            return $status === self::STATUS_ISSUED ? InvoiceStatus::Valid : InvoiceStatus::NeedsReconcile;
        }

        return match ($status) {
            self::STATUS_CANCELED => InvoiceStatus::Canceled,
            default => InvoiceStatus::Unknown,
        };
    }

    private function key(string $provider, string $idempotencyKey): string
    {
        return $provider."\0".$idempotencyKey;
    }
}
