<?php

declare(strict_types=1);

use App\Application\Marketing\AutorizarOrdenMembresiaUseCase;
use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class AuthorizeRecordingTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];

    /** @var list<array{status:int,body:string,error:string}> */
    public array $responses = [];

    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"status":"ok"}', 'error' => ''];
    }
}

final class AuthorizeMemOrderRepo implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $paid = false;
    public ?string $lastError = null;

    public function create(array $data): int
    {
        return 0;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return null;
    }

    public function markTransferNotified(int $orderId): void {}

    public function setApiActivationError(int $orderId, string $error): void
    {
        $this->lastError = $error;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['api_activation_error'] = $error;
        }
    }

    public function clearApiActivationError(int $orderId): void
    {
        $this->lastError = null;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['api_activation_error'] = null;
        }
    }

    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->paid = true;
        if (isset($this->rows[$orderId])) {
            $this->rows[$orderId]['status'] = 'paid';
            $this->rows[$orderId]['authorized_by'] = $authorizedBy;
            $this->rows[$orderId]['api_activation_error'] = null;
        }
    }

    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class SpyMembershipMailer implements MailerInterface
{
    /** @var list<MensajeCorreo> */
    public array $sent = [];

    public function enviar(MensajeCorreo $mensaje): void
    {
        $this->sent[] = $mensaje;
    }
}

final class AuthorizeNoOpLeadRepo implements LeadRepositoryInterface
{
    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 0; }
    public function findById(int $id): ?array { return null; }
    public function findByEmailVerifyToken(string $token): ?array { return null; }
    public function incrementEmailVerifyAttempts(int $leadId): void {}
    public function markEmailVerified(int $leadId): void {}
    public function markApiProvisioned(int $leadId, string $tenantPublicId, string $externalRef, string $instancePublicId = '', ?int $paqueteId = null, string $planSlug = 'demo', int $demoDays = 30): void {}
    public function markApiProvisionError(int $leadId, string $error): void {}
    public function markApiDeprovisionInitiated(int $leadId): void {}
    public function markApiDeprovisionCompleted(int $leadId): void {}
    public function findDemosOlderThanDays(int $days): array { return []; }
    public function findDemosExpired(): array { return []; }
    public function findPendingDeprovisions(): array { return []; }
    public function findDemoPackageBySlug(string $slug): ?array { return null; }
    public function findLatestByEmail(string $email): ?array { return null; }
    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void {}
    public function markCancelled(int $leadId): void {}
    public function clearCancelled(int $leadId): void {}
}

test('AutorizarOrdenMembresiaUseCase happy path activa plan y envía correo', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $transport->responses[] = ['status' => 200, 'body' => '{"token":"99|membership-token"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORD00000000000000000001',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'mensajes_mes_limite_snapshot' => 5000,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer($mailer), new AuthorizeNoOpLeadRepo()),
    );
    $uc->ejecutar(1, 7);

    assert_same(1, count($transport->calls));
    assert_true(str_contains($transport->calls[0]['url'], '/tenants/01JTENANT0000000000000001/activate-plan'));
    $body = json_decode((string) $transport->calls[0]['body'], true);
    assert_same('starter', $body['planSlug']);
    assert_same('monthly', $body['billingCycle']);
    assert_same('01JORD00000000000000000001', $body['orderExternalRef']);
    assert_true(! array_key_exists('messagesMonthlyLimit', $body), 'starter usa catálogo api, sin override');
    assert_true($orders->paid);
    assert_same(1, count($mailer->sent));
    assert_true(str_contains($mailer->sent[0]->html, '99|membership-token'));
});

test('AutorizarOrdenMembresiaUseCase deja orden desbloqueada si activate-plan falla', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $transport->responses[] = ['status' => 422, 'body' => '{"message":"unknown plan"}', 'error' => ''];
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[3] = [
        'id' => 3,
        'public_id' => '01JORD00000000000000000003',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];

    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer(new SpyMembershipMailer()), new AuthorizeNoOpLeadRepo()),
    );

    assert_throws(\App\Infrastructure\Integrations\LebytekApi\LebytekApiException::class, fn () => $uc->ejecutar(3, 7));
    assert_true($orders->paid === false);
    assert_same('pending_transfer', $orders->rows[3]['status']);
    assert_true(($orders->lastError ?? '') !== '');
});

test('AutorizarOrdenMembresiaUseCase falla sin tenant asociado', function (): void {
    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[2] = [
        'id' => 2,
        'public_id' => 'ORD2',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => null,
        'nombre' => 'X',
        'email' => 'x@test.com',
    ];

    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new ActivateMembershipFromOrderService(
            $orders,
            new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new AuthorizeRecordingTransport()),
            marketingMailRenderer(new SpyMembershipMailer()),
            new AuthorizeNoOpLeadRepo(),
        ),
    );

    assert_throws(\InvalidArgumentException::class, fn () => $uc->ejecutar(2, 1));
});

test('AutorizarOrdenMembresiaUseCase marca paid sin correo si api reusa activate-plan con token null', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $transport->responses[] = [
        'status' => 200,
        'body' => '{"token":null,"tenant":{"publicId":"01JTENANT0000000000000001","commercialStatus":"active","planSlug":"starter"},"plan":{"slug":"starter","name":"Starter","messagesMonthlyLimit":5000,"billingCycle":"monthly"}}',
        'error' => '',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[4] = [
        'id' => 4,
        'public_id' => '01JORD00000000000000000004',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'mensajes_mes_limite_snapshot' => 5000,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
        'api_activation_error' => 'API no devolvió token de membresía.',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer($mailer), new AuthorizeNoOpLeadRepo()),
    );
    $uc->ejecutar(4, 7);

    assert_true($orders->paid);
    assert_same('paid', $orders->rows[4]['status']);
    assert_same(0, count($mailer->sent), 'sin token nuevo no se reenvía email #3');
    assert_true(
        ($orders->rows[4]['api_activation_error'] ?? null) === null
            || $orders->rows[4]['api_activation_error'] === '',
        'markPaid must clear stale activation error'
    );
    assert_same(1, count($transport->calls), 'debió llamar activate-plan una vez');
});

test('AutorizarOrdenMembresiaUseCase rechaza slug demo sin llamar api', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[5] = [
        'id' => 5,
        'public_id' => '01JORD00000000000000000005',
        'paquete_slug' => 'demo',
        'ciclo' => 'monthly',
        'precio_snapshot' => 0,
        'mensajes_mes_limite_snapshot' => 100,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase(
        $orders,
        new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer($mailer), new AuthorizeNoOpLeadRepo()),
    );

    $thrown = false;
    try {
        $uc->ejecutar(5, 7);
    } catch (\InvalidArgumentException $e) {
        $thrown = true;
        assert_true(str_contains($e->getMessage(), 'autorizable') || str_contains($e->getMessage(), 'demo'));
    }
    assert_true($thrown, 'debe rechazar slug demo');
    assert_same(0, count($transport->calls), 'no llamar activate-plan con demo');
    assert_true(empty($orders->paid), 'orden no paid');
});
