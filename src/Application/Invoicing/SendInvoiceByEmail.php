<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Kernel\Config\Config;
use RuntimeException;

final readonly class SendInvoiceByEmail
{
    public function __construct(
        private InvoiceProviderRegistry $registry,
        private InvoiceIdResolver $resolver,
        private ?string $defaultProviderKey = null,
    ) {
    }

    public function handle(
        string $email,
        ?string $providerInvoiceId = null,
        ?string $sourceRef = null,
        ?string $providerKey = null,
    ): void {
        $resolvedProviderKey = $this->resolveProviderKey($providerKey);
        $resolvedProviderInvoiceId = $this->resolver->resolve($providerInvoiceId, $sourceRef);

        $this->registry->get($resolvedProviderKey)->sendByEmail($resolvedProviderInvoiceId, $email);
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
