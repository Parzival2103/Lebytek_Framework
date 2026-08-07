<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;

final class ReconcileIssuedInvoice
{
    public function __construct(
        private readonly InvoiceEventLogRepositoryInterface $events,
        private readonly InvoiceProviderRegistry $registry,
        private readonly ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(string $idempotencyKey, ?string $providerKey = null): IssuedInvoice
    {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $invoice = $this->events->findByIdempotencyKey($resolvedProviderKey, $idempotencyKey);
        if ($invoice === null) {
            throw new InvoiceSourceNotFound(sprintf(
                'Invoice idempotency key "%s" was not found for provider "%s"; claimed-only rows cannot be reconciled without a provider invoice id.',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        if ($invoice->status() !== InvoiceStatus::NeedsReconcile) {
            return $invoice;
        }

        $this->events->markIssued($resolvedProviderKey, $idempotencyKey, $invoice);
        $promoted = $this->events->findByIdempotencyKey($resolvedProviderKey, $idempotencyKey);
        if ($promoted === null) {
            throw new RuntimeException(sprintf(
                'Invoice idempotency key "%s" was promoted for provider "%s" but could not be reloaded.',
                $idempotencyKey,
                $resolvedProviderKey,
            ));
        }

        return $promoted;
    }

    /** @return IssuedInvoice[] */
    public function listNeedsReconcile(?string $providerKey = null, int $limit = 100): array
    {
        return $this->events->findNeedsReconcile($this->resolveProviderKey($providerKey), $limit);
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
