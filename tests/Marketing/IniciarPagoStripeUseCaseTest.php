<?php

declare(strict_types=1);

use App\Application\Marketing\IniciarPagoStripeUseCase;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class IniciarPagoStripeOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public ?string $savedRef = null;

    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void {}
    public function clearApiActivationError(int $orderId): void {}
    public function markPaid(int $orderId, int $authorizedBy): void {}
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}

    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void
    {
        $this->savedRef = $paymentRef;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['payment_provider'] = $provider;
            $this->rows[$orderId]['payment_ref'] = $paymentRef;
        }
    }

    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

test('IniciarPagoStripe arma CheckoutRequest con order public_id', function (): void {
    /** @var CheckoutRequest|null $seen */
    $seen = null;
    $gateway = new class($seen) implements PaymentGatewayInterface {
        public function __construct(private mixed &$seen) {}
        public function key(): string { return 'stripe'; }

        public function createCheckout(CheckoutRequest $request): CheckoutSession
        {
            $this->seen = $request;

            return new CheckoutSession('cs_test_123', 'https://checkout.stripe.test/pay');
        }

        public function parseWebhook(string $payload, string $signature): PaymentEvent
        {
            return new PaymentEvent(PaymentEventType::Ignored, 'x', '', Money::fromMajor(0, 'mxn'), 'x');
        }
    };
    $registry = new PaymentGatewayRegistry([
        'stripe' => ['driver' => 'stripe', 'factory' => fn () => $gateway],
    ]);
    $orders = new IniciarPagoStripeOrders();
    $orders->rows[9] = [
        'id' => 9,
        'public_id' => '01JORDSTRIPE0000000000001',
        'status' => 'pending_payment',
        'metodo_pago' => 'stripe',
        'precio_snapshot' => 2199,
        'paquete_slug' => 'starter',
        'email' => 'buyer@test.com',
    ];
    putenv('APP_URL=https://lebytek.test');
    putenv('PAYMENTS_CURRENCY=mxn');

    $url = (new IniciarPagoStripeUseCase($orders, $registry))->ejecutar(9);

    assert_same('https://checkout.stripe.test/pay', $url);
    assert_same('cs_test_123', $orders->savedRef);
    assert_true($seen instanceof CheckoutRequest);
    assert_same('01JORDSTRIPE0000000000001', $seen->externalRef());
    assert_same(219900, $seen->money()->amountMinor());
    assert_same('payment', $seen->mode());
    assert_same('01JORDSTRIPE0000000000001', $seen->metadata()['order_public_id']);
});

test('IniciarPagoStripe rejects orders that are not pending Stripe payments', function (): void {
    $orders = new IniciarPagoStripeOrders();
    $orders->rows[1] = ['status' => 'paid', 'metodo_pago' => 'stripe'];
    $orders->rows[2] = ['status' => 'pending_payment', 'metodo_pago' => 'transferencia'];
    $registry = new PaymentGatewayRegistry([]);
    $useCase = new IniciarPagoStripeUseCase($orders, $registry);

    assert_throws(\InvalidArgumentException::class, fn () => $useCase->ejecutar(1));
    assert_throws(\InvalidArgumentException::class, fn () => $useCase->ejecutar(2));
});
