<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use InvalidArgumentException;
use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;

final readonly class DownloadInvoiceDocument
{
    public function __construct(
        private InvoiceProviderRegistry $registry,
        private InvoiceIdResolver $resolver,
        private ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(
        string $format,
        ?string $providerInvoiceId = null,
        ?string $sourceRef = null,
        ?string $providerKey = null,
    ): string {
        $resolvedFormat = strtolower(trim($format));
        if (! in_array($resolvedFormat, ['pdf', 'xml'], true)) {
            throw new InvalidArgumentException('Invoice document format must be "pdf" or "xml".');
        }

        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $resolvedProviderInvoiceId = $this->resolver->resolve($providerInvoiceId, $sourceRef);
        $provider = $this->registry->get($resolvedProviderKey);

        return $resolvedFormat === 'pdf'
            ? $provider->downloadPdf($resolvedProviderInvoiceId)
            : $provider->downloadXml($resolvedProviderInvoiceId);
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
