<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class Address
{
    public function __construct(
        private string $zip,
        private string $country = 'MEX',
        private ?string $street = null,
    ) {}

    public function zip(): string
    {
        return $this->zip;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function street(): ?string
    {
        return $this->street;
    }
}
