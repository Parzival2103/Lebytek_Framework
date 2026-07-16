<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class Money
{
    private string $currency;

    public function __construct(
        private int $amountMinor,
        string $currency,
    ) {
        $normalized = strtolower($currency);
        if ($normalized !== 'mxn') {
            throw new \InvalidArgumentException('v1 payments only support mxn currency');
        }
        $this->currency = $normalized;
    }

    public static function fromMajor(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function amountMinor(): int { return $this->amountMinor; }
    public function currency(): string { return $this->currency; }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}
