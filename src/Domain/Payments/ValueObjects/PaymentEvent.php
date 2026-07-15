<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments\ValueObjects;

use Lebytek\Framework\Domain\Payments\PaymentEventType;

final readonly class PaymentEvent
{
    public function __construct(
        private PaymentEventType $type,
        private string $providerEventId,
        private string $externalRef,
        private Money $money,
        private string $rawStatus,
    ) {}

    public function type(): PaymentEventType { return $this->type; }
    public function providerEventId(): string { return $this->providerEventId; }
    public function externalRef(): string { return $this->externalRef; }
    public function money(): Money { return $this->money; }
    public function rawStatus(): string { return $this->rawStatus; }
}
