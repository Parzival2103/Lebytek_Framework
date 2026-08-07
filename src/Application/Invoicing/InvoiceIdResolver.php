<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceAmbiguousSource;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceSourceNotFound;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;

final readonly class InvoiceIdResolver
{
    public function __construct(private InvoiceEventLogRepositoryInterface $events)
    {
    }

    public function resolve(?string $providerInvoiceId = null, ?string $sourceRef = null): string
    {
        $directProviderInvoiceId = trim((string) $providerInvoiceId);
        if ($directProviderInvoiceId !== '') {
            return $directProviderInvoiceId;
        }

        $resolvedSourceRef = trim((string) $sourceRef);
        if ($resolvedSourceRef === '') {
            throw new InvoiceSourceNotFound('Invoice provider id or source_ref is required.');
        }

        $matches = $this->events->findIssuedBySourceRef($resolvedSourceRef);
        $count = count($matches);
        if ($count === 0) {
            throw new InvoiceSourceNotFound(sprintf('Invoice source_ref "%s" was not found.', $resolvedSourceRef));
        }

        if ($count > 1) {
            throw new InvoiceAmbiguousSource(sprintf(
                'Invoice source_ref "%s" matched %d issued invoices.',
                $resolvedSourceRef,
                $count,
            ));
        }

        return $matches[0]->providerInvoiceId();
    }
}
