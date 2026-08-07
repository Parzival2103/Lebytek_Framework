<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

final readonly class Money
{
    private string $currency;

    public function __construct(
        private int $amountMinor,
        string $currency,
    ) {
        $normalized = strtoupper(trim($currency));
        if ($normalized === '') {
            throw new \InvalidArgumentException('currency must not be empty');
        }
        $this->currency = $normalized;
    }

    public static function fromMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}
