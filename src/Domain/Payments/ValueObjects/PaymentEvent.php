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
    ) {}

    public function type(): PaymentEventType { return $this->type; }
    public function providerEventId(): string { return $this->providerEventId; }
    public function externalRef(): string { return $this->externalRef; }
    public function money(): Money { return $this->money; }
    public function rawStatus(): string { return $this->rawStatus; }
}
