<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAlreadyProcessed;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNotCancellable;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceClaimRow;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;
use Throwable;

final readonly class CancelIssuedInvoice
{
    public function __construct(
        private InvoiceProviderRegistry $registry,
        private InvoiceIdResolver $resolver,
        private InvoiceEventLogRepositoryInterface $events,
        private ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(
        InvoiceCancellation $cancellation,
        ?string $providerInvoiceId = null,
        ?string $sourceRef = null,
        ?string $providerKey = null,
    ): IssuedInvoice {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $resolvedProviderInvoiceId = $this->resolver->resolve($providerInvoiceId, $sourceRef);
        $provider = $this->registry->get($resolvedProviderKey);
        $issueRow = $this->events->findIssueByProviderInvoiceId($resolvedProviderKey, $resolvedProviderInvoiceId);
        if ($issueRow === null) {
            throw new InvoiceSourceNotFound(sprintf(
                'Invoice "%s" was not found in the invoice event ledger.',
                $resolvedProviderInvoiceId,
            ));
        }

        if ($issueRow->ledgerStatus() === 'canceled') {
            return $this->localCanceledSnapshot($resolvedProviderKey, $issueRow);
        }

        $cancelKey = 'cancel:' . $resolvedProviderInvoiceId;
        $claimed = $this->events->tryClaim(
            provider: $resolvedProviderKey,
            idempotencyKey: $cancelKey,
            sourceRef: $issueRow->sourceRef() ?? trim((string) $sourceRef),
            type: 'cancel',
            meta: $this->cancelClaimMeta($resolvedProviderInvoiceId, $cancellation),
        );

        if (! $claimed) {
            return $this->resolveExistingCancelClaim(
                $provider,
                $resolvedProviderKey,
                $resolvedProviderInvoiceId,
                $issueRow,
            );
        }

        try {
            $canceled = $provider->cancelInvoice($resolvedProviderInvoiceId, $cancellation);
        } catch (InvoiceNotCancellable $exception) {
            throw $exception;
        } catch (InvoiceProviderException $exception) {
            if ($this->isNotCancellableFailure($exception)) {
                throw new InvoiceNotCancellable(
                    sprintf('Invoice "%s" is not cancellable.', $resolvedProviderInvoiceId),
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $this->events->markCanceled($resolvedProviderKey, $issueRow->idempotencyKey(), $canceled);
        $this->markCancelClaimIssued($resolvedProviderKey, $cancelKey, $canceled);

        return $canceled;
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

    private function resolveExistingCancelClaim(
        InvoiceProviderInterface $provider,
        string $providerKey,
        string $providerInvoiceId,
        InvoiceClaimRow $issueRow,
    ): IssuedInvoice {
        $latestIssueRow = $this->events->findIssueByProviderInvoiceId($providerKey, $providerInvoiceId) ?? $issueRow;
        if ($latestIssueRow->ledgerStatus() === 'canceled') {
            return $this->localCanceledSnapshot($providerKey, $latestIssueRow);
        }

        try {
            $remote = $provider->retrieveInvoice($providerInvoiceId);
        } catch (Throwable $exception) {
            throw new InvoiceAlreadyProcessed(
                sprintf('Cancellation for invoice "%s" is already in-flight.', $providerInvoiceId),
                previous: $exception,
            );
        }

        if ($remote->status() === InvoiceStatus::Canceled) {
            $this->events->markCanceled($providerKey, $latestIssueRow->idempotencyKey(), $remote);
            $this->markCancelClaimIssued($providerKey, 'cancel:' . $providerInvoiceId, $remote);

            return $remote;
        }

        throw new InvoiceAlreadyProcessed(sprintf(
            'Cancellation for invoice "%s" is already in-flight.',
            $providerInvoiceId,
        ));
    }

    private function localCanceledSnapshot(string $providerKey, InvoiceClaimRow $issueRow): IssuedInvoice
    {
        $local = $this->events->findByIdempotencyKey($providerKey, $issueRow->idempotencyKey());
        if ($local instanceof IssuedInvoice && $local->status() === InvoiceStatus::Canceled) {
            return $local;
        }

        return new IssuedInvoice(
            providerInvoiceId: (string) $issueRow->providerInvoiceId(),
            uuid: '',
            status: InvoiceStatus::Canceled,
            sourceRef: $issueRow->sourceRef(),
            meta: $issueRow->meta(),
        );
    }

    /** @return array<string, mixed> */
    private function cancelClaimMeta(string $providerInvoiceId, InvoiceCancellation $cancellation): array
    {
        $meta = [
            'providerInvoiceId' => $providerInvoiceId,
            'motive' => $cancellation->motive(),
        ];

        if ($cancellation->substitution() !== null) {
            $meta['substitution'] = $cancellation->substitution();
        }

        return $meta;
    }

    private function markCancelClaimIssued(string $providerKey, string $cancelKey, IssuedInvoice $canceled): void
    {
        try {
            $this->events->markIssued($providerKey, $cancelKey, new IssuedInvoice(
                providerInvoiceId: $canceled->providerInvoiceId(),
                uuid: $canceled->uuid(),
                status: $canceled->status(),
                folioNumber: $canceled->folioNumber(),
                sourceRef: $canceled->sourceRef(),
                pdfUrl: $canceled->pdfUrl(),
                xmlUrl: $canceled->xmlUrl(),
                meta: array_merge($canceled->meta(), [
                    'provider_status' => $canceled->status()->value,
                ]),
            ));
        } catch (Throwable) {
            // The issue row is the fiscal source of truth; cancel-claim success metadata is audit-only.
        }
    }

    private function isNotCancellableFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $message = strtolower($current->getMessage());
            foreach ([
                'not cancellable',
                'not cancelable',
                'not-cancellable',
                'not-cancelable',
                'cannot cancel',
                'cannot be canceled',
                'cannot be cancelled',
            ] as $needle) {
                if (str_contains($message, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
