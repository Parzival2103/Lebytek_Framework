<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

function fakeGateway(string $key): PaymentGatewayInterface
{
    return new class($key) implements PaymentGatewayInterface {
        public function __construct(private string $k) {}
        public function key(): string { return $this->k; }
        public function createCheckout(CheckoutRequest $r): CheckoutSession {
            return new CheckoutSession('sess_x', 'https://pay.example/redirect');
        }
        public function parseWebhook(string $p, string $s): PaymentEvent {
            return new PaymentEvent(PaymentEventType::CheckoutCompleted, 'evt', 'ref', Money::fromMajor(1, 'mxn'), 'ok');
        }
    };
}

test('PaymentGatewayRegistry memoiza gateways', function (): void {
    $registry = new PaymentGatewayRegistry([
        'stripe' => ['driver' => 'stripe', 'factory' => fn () => fakeGateway('stripe')],
    ]);
    assert_true($registry->has('stripe'));
    assert_true($registry->get('stripe') === $registry->get('stripe'));
    assert_same('stripe', $registry->driver('stripe'));
});
