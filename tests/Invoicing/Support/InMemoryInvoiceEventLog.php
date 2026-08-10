<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

final class InMemoryInvoiceEventLog implements InvoiceEventLogRepositoryInterface
{
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_ISSUED = 'issued';
    private const STATUS_NEEDS_RECONCILE = 'needs_reconcile';

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
            throw new RuntimeException(sprintf(
                'Cannot attach provider invoice id for provider "%s" and idempotency key "%s": existing provider_invoice_id "%s" differs from "%s".',
                $provider,
                $idempotencyKey,
                (string) $currentProviderInvoiceId,
                $providerInvoiceId,
            ));
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

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeMetaPreservingExternalId(array $existing, array $incoming): array
    {
        $merged = array_merge($existing, $incoming);
        if (array_key_exists('external_id', $existing)) {
            $merged['external_id'] = $existing['external_id'];
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

        $this->rows[$key]['providerInvoiceId'] = $invoice->providerInvoiceId();
        $this->rows[$key]['uuid'] = $invoice->uuid();
        $this->rows[$key]['folioNumber'] = $invoice->folioNumber();
        $this->rows[$key]['status'] = $status;
        $this->rows[$key]['sourceRef'] = $invoice->sourceRef() ?? $this->rows[$key]['sourceRef'];
        $this->rows[$key]['meta'] = $invoice->meta();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): IssuedInvoice
    {
        return new IssuedInvoice(
            (string) $row['providerInvoiceId'],
            (string) $row['uuid'],
            $this->domainStatus((string) $row['status']),
            $row['folioNumber'] !== null ? (string) $row['folioNumber'] : null,
            $row['sourceRef'] !== null ? (string) $row['sourceRef'] : null,
            meta: is_array($row['meta']) ? $row['meta'] : [],
        );
    }

    private function domainStatus(string $status): InvoiceStatus
    {
        return match ($status) {
            self::STATUS_ISSUED => InvoiceStatus::Valid,
            self::STATUS_NEEDS_RECONCILE => InvoiceStatus::NeedsReconcile,
            'canceled' => InvoiceStatus::Canceled,
            default => InvoiceStatus::Unknown,
        };
    }

    private function key(string $provider, string $idempotencyKey): string
    {
        return $provider."\0".$idempotencyKey;
    }
}
