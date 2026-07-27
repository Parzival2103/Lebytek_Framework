<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

use Lebytek\Framework\Domain\Payments\PaymentEventType;

/** PHP 8.1: no `readonly class` (8.2+); use promoted `readonly` props. */
final class PaymentEvent
{
    public function __construct(
        private readonly PaymentEventType $type,
        private readonly string $providerEventId,
        private readonly string $externalRef,
        private readonly Money $money,
        private readonly string $rawStatus,
        private readonly ?string $subscriptionId = null,
        private readonly ?string $customerId = null,
        private readonly string $checkoutMode = 'payment',
        private readonly ?string $membresiaId = null,
    ) {}

    public function type(): PaymentEventType { return $this->type; }
    public function providerEventId(): string { return $this->providerEventId; }
    public function externalRef(): string { return $this->externalRef; }
    public function money(): Money { return $this->money; }
    public function rawStatus(): string { return $this->rawStatus; }
    public function subscriptionId(): ?string { return $this->subscriptionId; }
    public function customerId(): ?string { return $this->customerId; }
    public function checkoutMode(): string { return $this->checkoutMode; }
    public function membresiaId(): ?string { return $this->membresiaId; }
}
