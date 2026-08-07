<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAlreadyProcessed;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;
use Throwable;

final class IssueInvoiceFromSource
{
    public function __construct(
        private readonly InvoiceableSourceInterface $source,
        private readonly InvoiceEventLogRepositoryInterface $events,
        private readonly InvoiceProviderRegistry $registry,
        private readonly InvoiceDraftValidator $validator,
        private readonly ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(string $sourceRef, string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
    {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $provider = $this->registry->get($resolvedProviderKey);

        if (! $this->events->tryClaim($resolvedProviderKey, $idempotencyKey, $sourceRef, 'invoice')) {
            $existing = $this->events->findByIdempotencyKey($resolvedProviderKey, $idempotencyKey);
            if ($existing !== null && $existing->providerInvoiceId() !== '') {
                return $existing;
            }

            throw new InvoiceAlreadyProcessed(sprintf(
                'Invoice idempotency key "%s" is already claimed for provider "%s".',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        $observedInvoice = null;
        try {
            $draft = $this->source->findDraft($sourceRef);
            if ($draft === null) {
                throw new InvoiceSourceNotFound(sprintf('Invoice source "%s" was not found.', $sourceRef));
            }

            $this->validator->validate($draft);
            $observedInvoice = $provider->createInvoice($draft);
            $this->events->markIssued($resolvedProviderKey, $idempotencyKey, $observedInvoice);

            return $observedInvoice;
        } catch (Throwable $e) {
            if ($observedInvoice instanceof IssuedInvoice) {
                try {
                    $this->events->markNeedsReconcile($resolvedProviderKey, $idempotencyKey, $observedInvoice);
                } catch (Throwable) {
                    // Local reconcile mark failed; still surface InvoiceNeedsReconcile for the remote invoice.
                }

                throw new InvoiceNeedsReconcile(
                    sprintf(
                        'Invoice "%s" was created remotely but local issue mark failed.',
                        $observedInvoice->providerInvoiceId(),
                    ),
                    previous: $e,
                );
            }

            $this->events->releaseClaim($resolvedProviderKey, $idempotencyKey);
            throw $e;
        }
    }

    private function resolveProviderKey(?string $providerKey): string
    {
        $resolved = $providerKey ?? $this->defaultProviderKey ?? Config::get('invoicing.default', 'facturapi');
        $resolved = trim((string) $resolved);
        if ($resolved === '') {
            throw new \RuntimeException('Default invoice provider is not configured.');
        }

        return $resolved;
    }
}
