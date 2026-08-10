<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Invoicing;

use DateTimeImmutable;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderIdConflict;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Database\Connection;
use PDO;
use PDOException;
use RuntimeException;

final class PdoInvoiceEventLogRepository implements InvoiceEventLogRepositoryInterface
{
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_ISSUED = 'issued';
    private const STATUS_NEEDS_RECONCILE = 'needs_reconcile';
    private const STATUS_CANCELED = 'canceled';

    private const CLAIM_ROW_COLUMNS = 'provider, idempotency_key, source_ref, type, status, provider_invoice_id, meta, created_at';

    public function hasProcessed(string $provider, string $idempotencyKey): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
               AND provider_invoice_id IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function tryClaim(
        string $provider,
        string $idempotencyKey,
        string $sourceRef,
        string $type,
        array $meta = [],
    ): bool {
        $pdo = Connection::getInstance();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO inv_events (provider, idempotency_key, source_ref, type, status, meta)
                 VALUES (:provider, :idempotency_key, :source_ref, :type, :status, :meta)'
            );
            $stmt->execute([
                'provider' => $provider,
                'idempotency_key' => $idempotencyKey,
                'source_ref' => $sourceRef !== '' ? $sourceRef : null,
                'type' => $type,
                'status' => self::STATUS_CLAIMED,
                'meta' => $this->encodeMeta($meta),
            ]);

            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return false;
            }

            throw $e;
        }
    }

    public function releaseClaim(string $provider, string $idempotencyKey): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'DELETE FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
               AND status = :status
               AND provider_invoice_id IS NULL'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
            'status' => self::STATUS_CLAIMED,
        ]);
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
        $pdo = Connection::getInstance();
        $existingMeta = $this->assertCanAttachProviderInvoiceId($pdo, $provider, $idempotencyKey, $providerInvoiceId);
        $mergedMeta = $this->mergeMetaPreservingExternalId($existingMeta, $meta);

        $stmt = $pdo->prepare(
            'UPDATE inv_events
             SET provider_invoice_id = :provider_invoice_id,
                 status = :status,
                 meta = :meta,
                 updated_at = CURRENT_TIMESTAMP
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
               AND (
                   provider_invoice_id IS NULL
                   OR provider_invoice_id = :same_provider_invoice_id
               )'
        );
        $stmt->execute([
            'provider_invoice_id' => $providerInvoiceId,
            'same_provider_invoice_id' => $providerInvoiceId,
            'status' => self::STATUS_NEEDS_RECONCILE,
            'meta' => $this->encodeMeta($mergedMeta),
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($stmt->rowCount() === 0) {
            $this->assertCanAttachProviderInvoiceId($pdo, $provider, $idempotencyKey, $providerInvoiceId);
        }
    }

    public function findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT provider_invoice_id, uuid, folio_number, source_ref, status, meta
             FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
               AND provider_invoice_id IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findIssuedBySourceRef(string $sourceRef): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT provider_invoice_id, uuid, folio_number, source_ref, status, meta
             FROM inv_events
             WHERE source_ref = :source_ref
               AND provider_invoice_id IS NOT NULL
             ORDER BY id ASC'
        );
        $stmt->execute(['source_ref' => $sourceRef]);

        return array_map(
            fn (array $row): IssuedInvoice => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findNeedsReconcile(string $provider, int $limit = 100): array
    {
        if ($limit <= 0) {
            return [];
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT provider_invoice_id, uuid, folio_number, source_ref, status, meta
             FROM inv_events
             WHERE provider = :provider
               AND status = :status
               AND provider_invoice_id IS NOT NULL
             ORDER BY id ASC
             LIMIT '.max(1, $limit)
        );
        $stmt->execute([
            'provider' => $provider,
            'status' => self::STATUS_NEEDS_RECONCILE,
        ]);

        return array_map(
            fn (array $row): IssuedInvoice => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findClaimByIdempotencyKey(string $provider, string $idempotencyKey): ?InvoiceClaimRow
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT '.self::CLAIM_ROW_COLUMNS.'
             FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateClaimRow($row) : null;
    }

    public function findIssueByProviderInvoiceId(string $provider, string $providerInvoiceId): ?InvoiceClaimRow
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT '.self::CLAIM_ROW_COLUMNS.'
             FROM inv_events
             WHERE provider = :provider
               AND provider_invoice_id = :provider_invoice_id
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'provider_invoice_id' => $providerInvoiceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateClaimRow($row) : null;
    }

    public function findOrphanClaims(string $provider, int $minAgeSeconds, int $limit = 100): array
    {
        if ($limit <= 0) {
            return [];
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT '.self::CLAIM_ROW_COLUMNS.'
             FROM inv_events
             WHERE provider = :provider
               AND status = :status
               AND provider_invoice_id IS NULL
               AND created_at <= NOW() - INTERVAL :min_age_seconds SECOND
             ORDER BY id ASC
             LIMIT '.max(1, $limit)
        );
        $stmt->bindValue('provider', $provider, PDO::PARAM_STR);
        $stmt->bindValue('status', self::STATUS_CLAIMED, PDO::PARAM_STR);
        $stmt->bindValue('min_age_seconds', max(0, $minAgeSeconds), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn (array $row): InvoiceClaimRow => $this->hydrateClaimRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function mark(string $provider, string $idempotencyKey, IssuedInvoice $invoice, string $status): void
    {
        $pdo = Connection::getInstance();
        $existingMeta = $this->assertCanMark($pdo, $provider, $idempotencyKey, $invoice);
        $mergedMeta = $this->mergeMetaPreservingExternalId($existingMeta, $invoice->meta());

        $stmt = $pdo->prepare(
            'UPDATE inv_events
             SET provider_invoice_id = :provider_invoice_id,
                 uuid = :uuid,
                 folio_number = :folio_number,
                 source_ref = COALESCE(:source_ref, source_ref),
                 status = :status,
                 meta = :meta,
                 updated_at = CURRENT_TIMESTAMP
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
               AND (
                   provider_invoice_id IS NULL
                   OR provider_invoice_id = :empty_provider_invoice_id
                   OR provider_invoice_id = :provider_invoice_id_match
               )'
        );
        $stmt->execute([
            'provider_invoice_id' => $invoice->providerInvoiceId(),
            'provider_invoice_id_match' => $invoice->providerInvoiceId(),
            'empty_provider_invoice_id' => '',
            'uuid' => $invoice->uuid() !== '' ? $invoice->uuid() : null,
            'folio_number' => $invoice->folioNumber(),
            'source_ref' => $invoice->sourceRef(),
            'status' => $status,
            'meta' => $this->encodeMeta($mergedMeta),
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($stmt->rowCount() === 0) {
            $this->assertCanMark($pdo, $provider, $idempotencyKey, $invoice);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertCanAttachProviderInvoiceId(
        PDO $pdo,
        string $provider,
        string $idempotencyKey,
        string $providerInvoiceId,
    ): array {
        $stmt = $pdo->prepare(
            'SELECT provider_invoice_id, meta
             FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            throw new RuntimeException(sprintf(
                'Cannot attach provider invoice id for provider "%s" and idempotency key "%s": claim row not found.',
                $provider,
                $idempotencyKey,
            ));
        }

        $currentProviderInvoiceId = $row['provider_invoice_id'] !== null
            ? (string) $row['provider_invoice_id']
            : null;
        if (
            $currentProviderInvoiceId !== null
            && $currentProviderInvoiceId !== $providerInvoiceId
        ) {
            throw new InvoiceProviderIdConflict(
                sprintf(
                    'Cannot attach provider invoice id for provider "%s" and idempotency key "%s": existing provider_invoice_id "%s" differs from "%s".',
                    $provider,
                    $idempotencyKey,
                    $currentProviderInvoiceId,
                    $providerInvoiceId,
                ),
                $provider,
                $idempotencyKey,
                $currentProviderInvoiceId,
                $providerInvoiceId,
            );
        }

        return $this->decodeMeta($row['meta'] ?? null);
    }

    /**
     * @return array<string, mixed> existing meta, so callers can merge on top (A25)
     */
    private function assertCanMark(PDO $pdo, string $provider, string $idempotencyKey, IssuedInvoice $invoice): array
    {
        $stmt = $pdo->prepare(
            'SELECT provider_invoice_id, meta
             FROM inv_events
             WHERE provider = :provider
               AND idempotency_key = :idempotency_key
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => $provider,
            'idempotency_key' => $idempotencyKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            throw new RuntimeException(sprintf(
                'Cannot mark invoice event for provider "%s" and idempotency key "%s": claim row not found.',
                $provider,
                $idempotencyKey,
            ));
        }

        $currentProviderInvoiceId = $row['provider_invoice_id'] !== null
            ? (string) $row['provider_invoice_id']
            : null;
        if (
            $currentProviderInvoiceId !== null
            && $currentProviderInvoiceId !== ''
            && $currentProviderInvoiceId !== $invoice->providerInvoiceId()
        ) {
            throw new RuntimeException(sprintf(
                'Cannot mark invoice event for provider "%s" and idempotency key "%s": existing provider_invoice_id "%s" differs from "%s".',
                $provider,
                $idempotencyKey,
                $currentProviderInvoiceId,
                $invoice->providerInvoiceId(),
            ));
        }

        return $this->decodeMeta($row['meta'] ?? null);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): IssuedInvoice
    {
        $meta = $this->decodeMeta($row['meta'] ?? null);

        return new IssuedInvoice(
            (string) $row['provider_invoice_id'],
            (string) ($row['uuid'] ?? ''),
            $this->domainStatus((string) $row['status'], $meta),
            $row['folio_number'] !== null ? (string) $row['folio_number'] : null,
            $row['source_ref'] !== null ? (string) $row['source_ref'] : null,
            meta: $meta,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateClaimRow(array $row): InvoiceClaimRow
    {
        return new InvoiceClaimRow(
            (string) $row['provider'],
            (string) $row['idempotency_key'],
            $row['source_ref'] !== null ? (string) $row['source_ref'] : null,
            (string) $row['type'],
            (string) $row['status'],
            $row['provider_invoice_id'] !== null ? (string) $row['provider_invoice_id'] : null,
            $this->decodeMeta($row['meta'] ?? null),
            new DateTimeImmutable((string) $row['created_at']),
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

    /**
     * @param array<string, mixed> $meta
     */
    private function encodeMeta(array $meta): ?string
    {
        $meta = InvoiceSecretScrubber::scrubMetaSecrets($meta);

        return $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $meta): array
    {
        if (! is_string($meta) || $meta === '') {
            return [];
        }

        $decoded = json_decode($meta, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
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
}
