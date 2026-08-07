<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;

final readonly class InvoiceDraft
{
    /** @param InvoiceItem[] $items */
    /** @param array<string, mixed> $metadata */
    public function __construct(
        private string $sourceRef,
        private FiscalCustomer $customer,
        private array $items,
        private PaymentForm $paymentForm,
        private CfdiUse $cfdiUse = CfdiUse::G01,
        private string $currency = 'MXN',
        private array $metadata = [],
    ) {}

    public function sourceRef(): string
    {
        return $this->sourceRef;
    }

    public function customer(): FiscalCustomer
    {
        return $this->customer;
    }

    /** @return InvoiceItem[] */
    public function items(): array
    {
        return $this->items;
    }

    public function paymentForm(): PaymentForm
    {
        return $this->paymentForm;
    }

    public function cfdiUse(): CfdiUse
    {
        return $this->cfdiUse;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
