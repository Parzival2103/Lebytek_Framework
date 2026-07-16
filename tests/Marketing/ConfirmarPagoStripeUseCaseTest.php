<?php
declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Application\Marketing\ConfirmarPagoStripeUseCase;
use App\Application\Marketing\MembershipOrderActors;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class ConfirmEventLog implements PaymentEventLogRepositoryInterface
{
    /** @var array<string, true> */
    public array $claimed = [];
    public function hasProcessed(string $provider, string $eventId): bool
    {
        return isset($this->claimed[$provider."\0".$eventId]);
    }
    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $k = $provider."\0".$eventId;
        if (isset($this->claimed[$k])) {
            return false;
        }
        $this->claimed[$k] = true;
        return true;
    }
}

final class ConfirmOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $byPublic = [];
    public bool $paid = false;
    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array
    {
        foreach ($this->byPublic as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
    public function findByPublicId(string $publicId): ?array { return $this->byPublic[$publicId] ?? null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void
    {
        foreach ($this->byPublic as $k => $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                $this->byPublic[$k]['api_activation_error'] = $error;
            }
        }
    }
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        foreach ($this->byPublic as $k => $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                $this->byPublic[$k]['status'] = 'paid';
                $this->byPublic[$k]['authorized_by'] = $authorizedBy;
            }
        }
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ConfirmRecTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];
    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"token":"t"}', 'error' => ''];
    }
}

final class ConfirmMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

function confirmPendingOrder(array $over = []): array
{
    return array_merge([
        'id' => 1,
        'public_id' => '01JORDPAY00000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_payment',
        'metodo_pago' => 'stripe',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ], $over);
}

function makeConfirmar(ConfirmOrders $orders, ConfirmEventLog $log, ConfirmRecTransport $transport): ConfirmarPagoStripeUseCase
{
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $activator = new ActivateMembershipFromOrderService($orders, $api, new ConfirmMailer());
    return new ConfirmarPagoStripeUseCase($orders, $log, $activator);
}

test('evento duplicado no reactiva', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"token":"t1"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $event = new PaymentEvent(PaymentEventType::CheckoutCompleted, 'evt_dup', '01JORDPAY00000000000000001', Money::fromMajor(2199, 'mxn'), 'paid');
    $uc->ejecutar($event);
    $uc->ejecutar($event);
    assert_same(1, count($transport->calls));
    assert_true($orders->paid);
});

test('Ignored no llama activate-plan', function (): void {
    $orders = new ConfirmOrders();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(PaymentEventType::Ignored, 'evt_noise', '', Money::fromMajor(0, 'mxn'), 'customer.created'));
    assert_same(0, count($transport->calls));
    assert_true($log->hasProcessed('stripe', 'evt_noise'));
});

test('mismatch de monto no marca paid ni activa', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_bad_amt',
        '01JORDPAY00000000000000001',
        new Money(100, 'mxn'),
        'paid',
    ));
    assert_true(! $orders->paid);
    assert_same(0, count($transport->calls));
    assert_true(isset($orders->byPublic['01JORDPAY00000000000000001']['api_activation_error']));
});

test('CheckoutCompleted con tenant marca paid y activa una vez', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 201, 'body' => '{"token":"tok"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_ok',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_same(1, count($transport->calls));
    $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORDPAY00000000000000001');
    assert_true(str_contains(implode("\n", $transport->calls[0]['headers']), 'Idempotency-Key: '.$key));
    assert_same(MembershipOrderActors::SYSTEM_WEBHOOK, $orders->byPublic['01JORDPAY00000000000000001']['authorized_by']);
});

test('CheckoutCompleted sin tenant marca paid sin activate', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder(['api_tenant_public_id' => '']);
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_no_tenant',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_same(0, count($transport->calls));
});

test('orden ya paid es no-op de activate', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder(['status' => 'paid']);
    $orders->paid = true;
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_already',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_same(0, count($transport->calls));
});

test('fallo de activate tras claim no relanza', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_api_down',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
    ));
    assert_true($orders->paid);
    assert_true(isset($orders->byPublic['01JORDPAY00000000000000001']['api_activation_error']));
});

test('PaymentFailed deja pending_payment', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::PaymentFailed,
        'evt_fail',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'failed',
    ));
    assert_true(! $orders->paid);
    assert_same('pending_payment', $orders->byPublic['01JORDPAY00000000000000001']['status']);
    assert_same(0, count($transport->calls));
});
