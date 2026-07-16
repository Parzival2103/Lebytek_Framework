<?php

declare(strict_types=1);

use App\Application\Marketing\ActivateMembershipFromOrderService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class ConvLeadRepo implements LeadRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    public int $markConvertedCalls = 0;

    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
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

    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void
    {
        $this->markConvertedCalls++;
        if (! isset($this->rows[$leadId])) {
            return;
        }
        if (($this->rows[$leadId]['converted_at'] ?? null) === null) {
            $this->rows[$leadId]['converted_at'] = '2026-07-15 12:00:00';
        }
        $this->rows[$leadId]['plan_slug'] = $planSlug;
        $this->rows[$leadId]['demo_expires_at'] = null;
        if ($paqueteId !== null) {
            $this->rows[$leadId]['paquete_id'] = $paqueteId;
        }
    }

    public function markCancelled(int $leadId): void
    {
        if (isset($this->rows[$leadId])) {
            $this->rows[$leadId]['cancelled_at'] = '2026-07-15 12:00:00';
        }
    }

    public function clearCancelled(int $leadId): void
    {
        if (isset($this->rows[$leadId])) {
            $this->rows[$leadId]['cancelled_at'] = null;
        }
    }
}

final class ConvOrders implements MembershipOrderRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function create(array $data): int { return 0; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function findByPublicId(string $publicId): ?array { return null; }
    public function markTransferNotified(int $orderId): void {}
    public function setApiActivationError(int $orderId, string $error): void {}
    public function clearApiActivationError(int $orderId): void {}
    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $this->rows[$orderId]['status'] = 'paid';
    }
    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void {}
    public function markPaymentPending(int $orderId, array $patch): void {}
    public function savePaymentRef(int $orderId, string $provider, string $paymentRef): void {}
    public function findByPaymentRef(string $provider, string $paymentRef): ?array { return null; }
}

final class ConvTransport implements LebytekApiTransport
{
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        return ['status' => 200, 'body' => '{"token":"tok_plain","created":true}', 'error' => ''];
    }
}

final class ConvMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void {}
}

test('fromConfirmedPayment marca lead convertido con plan paid', function (): void {
    $leads = new ConvLeadRepo();
    $leads->rows[7] = [
        'id' => 7,
        'plan_slug' => 'demo',
        'converted_at' => null,
        'demo_expires_at' => '2026-08-01 00:00:00',
    ];
    $orders = new ConvOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JORDCONV',
        'lead_id' => 7,
        'api_tenant_public_id' => '01TENANT',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'nombre' => 'Ana',
        'email' => 'ana@example.com',
        'precio_snapshot' => 499,
        'status' => 'pending_payment',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new ConvTransport());
    $svc = new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer(new ConvMailer()), $leads);

    $svc->fromConfirmedPayment($orders->rows[1], 0, ActivateMembershipFromOrderService::stableActivateIdempotencyKey('01JORDCONV'));

    assert_same(1, $leads->markConvertedCalls);
    assert_same('starter', $leads->rows[7]['plan_slug']);
    assert_true($leads->rows[7]['converted_at'] !== null);
    assert_true($leads->rows[7]['demo_expires_at'] === null);
});

test('markConverted es idempotente si converted_at ya existe', function (): void {
    $leads = new ConvLeadRepo();
    $leads->rows[7] = [
        'id' => 7,
        'plan_slug' => 'starter',
        'converted_at' => '2026-07-01 10:00:00',
        'demo_expires_at' => null,
    ];
    $leads->markConverted(7, 'business');
    assert_same('2026-07-01 10:00:00', $leads->rows[7]['converted_at']);
    assert_same('business', $leads->rows[7]['plan_slug']);
});

test('activate sin lead_id no falla ni llama markConverted', function (): void {
    $leads = new ConvLeadRepo();
    $orders = new ConvOrders();
    $orders->rows[1] = [
        'id' => 1,
        'public_id' => '01JNLEAD',
        'lead_id' => null,
        'api_tenant_public_id' => '01TENANT',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'nombre' => 'Ana',
        'email' => 'ana@example.com',
        'precio_snapshot' => 499,
        'status' => 'pending_payment',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new ConvTransport());
    $svc = new ActivateMembershipFromOrderService($orders, $api, marketingMailRenderer(new ConvMailer()), $leads);
    $svc->fromConfirmedPayment($orders->rows[1], 0, 'key');
    assert_same(0, $leads->markConvertedCalls);
});
