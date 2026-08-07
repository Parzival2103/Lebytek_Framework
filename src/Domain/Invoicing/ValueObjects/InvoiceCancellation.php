<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class InvoiceCancellation
{
    public function __construct(
        private string $motive,
        private ?string $substitution = null,
    ) {}

    public function motive(): string
    {
        return $this->motive;
    }

    public function substitution(): ?string
    {
        return $this->substitution;
    }
}
