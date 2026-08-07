<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class FiscalCustomer
{
    public function __construct(
        private string $legalName,
        private string $taxId,
        private string $taxSystem,
        private Address $address,
        private ?string $email = null,
    ) {}

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function taxId(): string
    {
        return $this->taxId;
    }

    public function taxSystem(): string
    {
        return $this->taxSystem;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function email(): ?string
    {
        return $this->email;
    }
}
