<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class CheckoutRequest
{
    /** @param array<string, string> $metadata */
    public function __construct(
        private readonly Money $money,
        private readonly string $description,
        private readonly string $customerEmail,
        private readonly string $successUrl,
        private readonly string $cancelUrl,
        private readonly string $externalRef,
        private readonly array $metadata,
        private readonly string $mode,
        private readonly ?string $idempotencyKey = null,
    ) {
        if (! in_array($mode, ['payment', 'subscription'], true)) {
            throw new \InvalidArgumentException('mode must be payment or subscription');
        }
    }

    public function money(): Money { return $this->money; }
    public function description(): string { return $this->description; }
    public function customerEmail(): string { return $this->customerEmail; }
    public function successUrl(): string { return $this->successUrl; }
    public function cancelUrl(): string { return $this->cancelUrl; }
    public function externalRef(): string { return $this->externalRef; }
    /** @return array<string, string> */
    public function metadata(): array { return $this->metadata; }
    public function mode(): string { return $this->mode; }
    public function idempotencyKey(): ?string { return $this->idempotencyKey; }
}
