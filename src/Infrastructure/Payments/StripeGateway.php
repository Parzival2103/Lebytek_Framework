<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class StripeGateway implements PaymentGatewayInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function key(): string { return 'stripe'; }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        throw new \RuntimeException('StripeGateway not implemented');
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        throw new \RuntimeException('StripeGateway not implemented');
    }
}
