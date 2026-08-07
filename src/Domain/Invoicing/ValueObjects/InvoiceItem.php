<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class InvoiceItem
{
    /** @param InvoiceTax[] $taxes */
    public function __construct(
        private float $quantity,
        private string $description,
        private string $productKey,
        private Money $unitPrice,
        private array $taxes = [],
        private bool $taxExempt = false,
        private ?string $unitKey = null,
    ) {}

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function productKey(): string
    {
        return $this->productKey;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    /** @return InvoiceTax[] */
    public function taxes(): array
    {
        return $this->taxes;
    }

    public function taxExempt(): bool
    {
        return $this->taxExempt;
    }

    public function unitKey(): ?string
    {
        return $this->unitKey;
    }
}
