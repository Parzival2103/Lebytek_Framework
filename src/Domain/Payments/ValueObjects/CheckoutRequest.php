<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

final readonly class CheckoutRequest
{
    /** @param array<string, string> $metadata */
    public function __construct(
        private Money $money,
        private string $description,
        private string $customerEmail,
        private string $successUrl,
        private string $cancelUrl,
        private string $externalRef,
        private array $metadata,
        private string $mode,
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
}
