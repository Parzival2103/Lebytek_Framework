<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class InvoiceTax
{
    public function __construct(
        private string $type,
        private float $rate,
        private string $factor = 'Tasa',
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function rate(): float
    {
        return $this->rate;
    }

    public function factor(): string
    {
        return $this->factor;
    }
}
