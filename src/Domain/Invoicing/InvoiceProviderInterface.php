<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;

interface InvoiceProviderInterface
{
    public function key(): string;

    public function createInvoice(InvoiceDraft $draft): IssuedInvoice;

    public function cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice;

    public function downloadPdf(string $providerInvoiceId): string;

    public function downloadXml(string $providerInvoiceId): string;

    public function sendByEmail(string $providerInvoiceId, string $email): void;
}
