<?php
declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Application\Marketing\ConfirmarPagoStripeUseCase;
use App\Application\Marketing\MarketingMailRenderer;
use App\Application\Marketing\MembershipOrderActors;
use App\Application\Marketing\RecoverMembershipPaymentService;
use App\Application\Marketing\StartMembershipGraceService;
use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
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
    public function clearApiActivationError(int $orderId): void
    {
        foreach ($this->byPublic as $k => $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                $this->byPublic[$k]['api_activation_error'] = null;
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

final class ConfirmNoOpLeadRepo implements LeadRepositoryInterface
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

final class ConfirmMembershipRepo implements MembershipRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function upsertFromActivation(array $data): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, ['id' => $id]);
        return $id;
    }

    public function findByTenantPublicId(string $tenantPublicId): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['api_tenant_public_id'] ?? '') === $tenantPublicId) {
                return $row;
            }
        }
        return null;
    }

    public function findByStripeSubscriptionId(string $subscriptionId): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['stripe_subscription_id'] ?? null) === $subscriptionId) {
                return $row;
            }
        }
        return null;
    }

    public function findByRetryTokenHash(string $hash): ?array { return null; }
    public function findByReactivationTokenHash(string $hash): ?array { return null; }

    public function markPastDue(int $id, \DateTimeInterface $graceEndsAt, string $retryTokenHash): void
    {
        $this->rows[$id]['status'] = 'past_due';
        $this->rows[$id]['grace_ends_at'] = $graceEndsAt->format('Y-m-d H:i:s');
        $this->rows[$id]['retry_token_hash'] = $retryTokenHash;
    }

    public function clearGrace(int $id): void
    {
        $this->rows[$id]['status'] = 'active';
        unset($this->rows[$id]['grace_ends_at'], $this->rows[$id]['retry_token_hash']);
    }

    public function markCancelled(int $id, string $reactivationTokenHash): void
    {
        $this->rows[$id]['status'] = 'cancelled';
        $this->rows[$id]['reactivation_token_hash'] = $reactivationTokenHash;
    }

    public function markActive(int $id, ?\DateTimeInterface $periodEnd = null): void
    {
        $this->rows[$id]['status'] = 'active';
    }

    public function findGraceExpired(\DateTimeInterface $now): array { return []; }

    public function bindStripeSubscription(int $id, ?string $customerId, ?string $subscriptionId): void
    {
        if ($customerId !== null) {
            $this->rows[$id]['stripe_customer_id'] = $customerId;
        }
        if ($subscriptionId !== null) {
            $this->rows[$id]['stripe_subscription_id'] = $subscriptionId;
        }
    }
}

final class ConfirmChurnRepo implements ChurnMetricsRepositoryInterface
{
    public function countActiveDemos(): int { return 0; }
    public function countDemosExpiringWithinDays(int $days): int { return 0; }
    public function countOpenRiskSignals(): int { return 0; }
    public function getLatestChurnSnapshot(): ?array { return null; }
    public function findOpenRiskSignals(int $limit = 5): array { return []; }
    public function upsertRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType, string $severity = 'medium', ?array $payload = null): void {}
    public function resolveOpenRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType): void {}
    public function saveChurnSnapshot(array $data): void {}
    public function findRecentlyProvisioned(int $hours = 24): array { return []; }
    public function countDemosStarted(int $year, int $month): int { return 0; }
    public function countDemosConverted(int $year, int $month): int { return 0; }
    public function countClientsStart(int $year, int $month): int { return 0; }
    public function countClientsLost(int $year, int $month): int { return 0; }
    public function countActiveByUsage(int $year, int $month): int { return 0; }
}

final class ConfirmPlantillas implements PlantillaRepositoryInterface
{
    public function findActiveByClave(string $clave): ?array
    {
        return ['id' => 1, 'clave' => $clave, 'asunto' => 'T', 'cuerpo' => '{{nombre}}', 'activo' => 1];
    }
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

function makeConfirmar(ConfirmOrders $orders, ConfirmEventLog $log, ConfirmRecTransport $transport, ?ConfirmMembershipRepo $memberships = null): ConfirmarPagoStripeUseCase
{
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $mailer = new ConfirmMailer();
    $leads = new ConfirmNoOpLeadRepo();
    $memberships = $memberships ?? new ConfirmMembershipRepo();
    $churn = new ConfirmChurnRepo();
    $renderer = new MarketingMailRenderer(new ConfirmPlantillas(), $mailer);
    $activator = new ActivateMembershipFromOrderService($orders, $api, $renderer, $leads);
    $startGrace = new StartMembershipGraceService($memberships, $leads, $churn, $renderer);
    $recover = new RecoverMembershipPaymentService($memberships, $leads, $churn, $api, new PaymentGatewayRegistry([]));
    return new ConfirmarPagoStripeUseCase($orders, $log, $activator, $memberships, $startGrace, $recover);
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

test('InvoicePaymentFailed inicia gracia past_due', function (): void {
    $_ENV['APP_URL'] = 'https://lebytek.com';
    $orders = new ConfirmOrders();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $memberships = new ConfirmMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 7,
        'api_tenant_public_id' => '01JTENANT0000000000000001',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
        'stripe_subscription_id' => 'sub_test_abc123',
    ]);
    $uc = makeConfirmar($orders, $log, $transport, $memberships);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::InvoicePaymentFailed,
        'evt_inv_fail',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'open',
        'sub_test_abc123',
        'cus_test_xyz',
    ));
    assert_same('past_due', $memberships->rows[$id]['status']);
    assert_true(isset($memberships->rows[$id]['grace_ends_at']));
});

test('InvoicePaid recupera membresía past_due', function (): void {
    $orders = new ConfirmOrders();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $transport->responses[] = ['status' => 200, 'body' => '{"commercialStatus":"active"}', 'error' => ''];
    $memberships = new ConfirmMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 7,
        'api_tenant_public_id' => '01JTENANT0000000000000001',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
        'stripe_subscription_id' => 'sub_test_abc123',
        'status' => 'past_due',
    ]);
    $memberships->markPastDue($id, new DateTimeImmutable('+24 hours'), hash('sha256', 'retry'));
    $uc = makeConfirmar($orders, $log, $transport, $memberships);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::InvoicePaid,
        'evt_inv_paid',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
        'sub_test_abc123',
        'cus_test_xyz',
    ));
    assert_same('active', $memberships->rows[$id]['status']);
});

test('CheckoutCompleted con subscriptionId es no-op de checkout', function (): void {
    $orders = new ConfirmOrders();
    $orders->byPublic['01JORDPAY00000000000000001'] = confirmPendingOrder();
    $log = new ConfirmEventLog();
    $transport = new ConfirmRecTransport();
    $uc = makeConfirmar($orders, $log, $transport);
    $uc->ejecutar(new PaymentEvent(
        PaymentEventType::CheckoutCompleted,
        'evt_sub_checkout',
        '01JORDPAY00000000000000001',
        Money::fromMajor(2199, 'mxn'),
        'paid',
        'sub_new_checkout',
        'cus_new',
    ));
    assert_true(! $orders->paid);
    assert_same(0, count($transport->calls));
});
