<?php

declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Application\Marketing\MembershipOrderActors;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class ActRecTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];
    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{}', 'error' => ''];
    }
}

final class ActMemOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public bool $paid = false;

    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void
    {
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['api_activation_error'] = $error;
        }
    }
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['status'] = 'paid';
            $this->rows[$orderId]['authorized_by'] = $authorizedBy;
        }
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ActSpyMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

test('stableActivateIdempotencyKey es UUID determinista', function (): void {
    $a = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD');
    $b = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD');

    assert_same($a, $b);
    assert_true((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a));
});

test('fromConfirmedPayment marca paid aunque activate falle', function (): void {
    $transport = new ActRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $orders = new ActMemOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_payment',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ActSpyMailer());
    $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD00000000000000000001');

    assert_throws(
        \App\Infrastructure\Integrations\LebytekApi\LebytekApiException::class,
        fn () => $svc->fromConfirmedPayment($orders->rows[1], MembershipOrderActors::SYSTEM_WEBHOOK, $key),
    );
    assert_true($orders->paid);
    assert_true(str_contains(implode("\n", $transport->calls[0]['headers']), 'Idempotency-Key: '.$key));
    assert_true(isset($orders->rows[1]['api_activation_error']));
});

test('fromConfirmedPayment deja paid sin tenant para activación manual posterior', function (): void {
    $transport = new ActRecTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $orders = new ActMemOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_payment',
        'api_tenant_public_id' => null,
    ];
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ActSpyMailer());

    $svc->fromConfirmedPayment(
        $orders->rows[1],
        MembershipOrderActors::SYSTEM_WEBHOOK,
        ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORD00000000000000000001'),
    );

    assert_true($orders->paid);
    assert_same(0, count($transport->calls));
});

test('fromManualAuthorize no marca paid si api falla', function (): void {
    $transport = new ActRecTransport();
    $transport->responses[] = ['status' => 500, 'body' => '{"message":"down"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $orders = new ActMemOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];
    $svc = new ActivateMembershipFromOrderService($orders, $api, new ActSpyMailer());

    assert_throws(
        \App\Infrastructure\Integrations\LebytekApi\LebytekApiException::class,
        fn () => $svc->fromManualAuthorize($orders->rows[1], 7),
    );
    assert_true(! $orders->paid);
});
