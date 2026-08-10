<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceProviderEvent;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;
use Throwable;

final class ApplyInvoiceProviderEvent
{
    public function __construct(
        private readonly InvoiceEventLogRepositoryInterface $events,
        private readonly InvoiceProviderRegistry $registry,
        private readonly ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(InvoiceProviderEvent $event, ?string $providerKey = null): ?IssuedInvoice
    {
        if (! $this->isInvoiceEvent($event) || trim($event->providerInvoiceId()) === '') {
            return null;
        }

        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $webhookIdempotencyKey = 'webhook:' . $event->providerEventId();
        if (! $this->events->tryClaim($resolvedProviderKey, $webhookIdempotencyKey, '', 'webhook', $this->claimMeta($event))) {
            return null;
        }

        $issueRow = $this->events->findIssueByProviderInvoiceId($resolvedProviderKey, $event->providerInvoiceId());
        if ($issueRow === null) {
            return null;
        }

        $provider = $this->registry->get($resolvedProviderKey);
        $observedInvoice = $this->withEventStatus($this->observedInvoice($provider, $event), $event);
        if ($observedInvoice->status() === InvoiceStatus::Canceled) {
            $this->events->markCanceled($resolvedProviderKey, $issueRow->idempotencyKey(), $observedInvoice);
        } else {
            $this->events->markIssued($resolvedProviderKey, $issueRow->idempotencyKey(), $observedInvoice);
        }

        return $this->events->findByIdempotencyKey($resolvedProviderKey, $issueRow->idempotencyKey());
    }

    private function isInvoiceEvent(InvoiceProviderEvent $event): bool
    {
        $type = strtolower(trim($event->type()));

        return $type === 'invoice'
            || str_starts_with($type, 'invoice.')
            || str_starts_with($type, 'invoice_');
    }

    /**
     * @return array<string, mixed>
     */
    private function claimMeta(InvoiceProviderEvent $event): array
    {
        $meta = $event->meta();
        $meta['provider_event_id'] = $event->providerEventId();
        $meta['provider_event_type'] = $event->type();
        $meta['provider_invoice_id'] = $event->providerInvoiceId();
        if (trim($event->status()) !== '') {
            $meta['provider_status'] = $event->status();
        }

        return $meta;
    }

    private function observedInvoice(InvoiceProviderInterface $provider, InvoiceProviderEvent $event): IssuedInvoice
    {
        try {
            return $provider->retrieveInvoice($event->providerInvoiceId());
        } catch (Throwable) {
            $rawStatus = trim($event->status());

            return new IssuedInvoice(
                providerInvoiceId: $event->providerInvoiceId(),
                uuid: '',
                status: InvoiceStatus::fromProvider($rawStatus),
                meta: ['provider_status' => $rawStatus !== '' ? $rawStatus : InvoiceStatus::Unknown->value],
            );
        }
    }

    private function withEventStatus(IssuedInvoice $invoice, InvoiceProviderEvent $event): IssuedInvoice
    {
        $rawStatus = trim($event->status());
        if ($rawStatus === '') {
            return $invoice;
        }

        $meta = $invoice->meta();
        $meta['provider_status'] = $rawStatus;

        return new IssuedInvoice(
            providerInvoiceId: $invoice->providerInvoiceId(),
            uuid: $invoice->uuid(),
            status: InvoiceStatus::fromProvider($rawStatus),
            folioNumber: $invoice->folioNumber(),
            sourceRef: $invoice->sourceRef(),
            pdfUrl: $invoice->pdfUrl(),
            xmlUrl: $invoice->xmlUrl(),
            meta: $meta,
        );
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
