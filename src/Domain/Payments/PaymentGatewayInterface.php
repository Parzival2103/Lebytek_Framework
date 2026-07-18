<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

interface PaymentGatewayInterface
{
    public function key(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    public function parseWebhook(string $payload, string $signature): PaymentEvent;
}
