<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNotCancellable;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
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

        $this->auditCancelClaim($resolvedProviderKey, $resolvedProviderInvoiceId, $sourceRef, $canceled);

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

    private function auditCancelClaim(
        string $providerKey,
        string $providerInvoiceId,
        ?string $sourceRef,
        IssuedInvoice $canceled,
    ): void {
        try {
            $this->events->tryClaim(
                provider: $providerKey,
                idempotencyKey: 'cancel:' . $providerInvoiceId,
                sourceRef: trim((string) $sourceRef),
                type: 'cancel',
                meta: [
                    'providerInvoiceId' => $canceled->providerInvoiceId(),
                    'uuid' => $canceled->uuid(),
                    'status' => $canceled->status()->value,
                ],
            );
        } catch (Throwable) {
            // Cancel already succeeded remotely; audit failures are intentionally best-effort.
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
