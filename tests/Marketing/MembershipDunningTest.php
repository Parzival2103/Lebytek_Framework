<?php

declare(strict_types=1);

use App\Application\Marketing\ExpireMembershipGraceService;
use App\Application\Marketing\MarketingMailRenderer;
use App\Application\Marketing\RecoverMembershipPaymentService;
use App\Application\Marketing\StartMembershipGraceService;
use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;

final class DunningMembershipRepo implements MembershipRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    private int $nextId = 1;
    public int $markPastDueCalls = 0;
    public ?string $firstGraceEndsAt = null;

    public function upsertFromActivation(array $data): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, ['id' => $id, 'status' => 'active']);
        return $id;
    }

    public function findByTenantPublicId(string $tenantPublicId): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['api_tenant_public_id'] === $tenantPublicId) {
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

    public function findByRetryTokenHash(string $hash): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['retry_token_hash'] ?? null) === $hash) {
                return $row;
            }
        }
        return null;
    }

    public function findByReactivationTokenHash(string $hash): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['reactivation_token_hash'] ?? null) === $hash) {
                return $row;
            }
        }
        return null;
    }

    public function markPastDue(int $id, \DateTimeInterface $graceEndsAt, string $retryTokenHash): void
    {
        $this->markPastDueCalls++;
        if ($this->firstGraceEndsAt === null) {
            $this->firstGraceEndsAt = $graceEndsAt->format('Y-m-d H:i:s');
        }
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
        $this->rows[$id]['cancelled_at'] = date('Y-m-d H:i:s');
    }

    public function markActive(int $id, ?\DateTimeInterface $periodEnd = null): void
    {
        $this->rows[$id]['status'] = 'active';
    }

    public function findGraceExpired(\DateTimeInterface $now): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if (($row['status'] ?? '') !== 'past_due') {
                continue;
            }
            if (($row['grace_ends_at'] ?? '') <= $now->format('Y-m-d H:i:s')) {
                $out[] = $row;
            }
        }
        return $out;
    }

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

final class DunningLeadRepo implements LeadRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

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
    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void {}
    public function markCancelled(int $leadId): void
    {
        if (isset($this->rows[$leadId])) {
            $this->rows[$leadId]['cancelled_at'] = date('Y-m-d H:i:s');
        }
    }
    public function clearCancelled(int $leadId): void
    {
        if (isset($this->rows[$leadId])) {
            $this->rows[$leadId]['cancelled_at'] = null;
        }
    }
}

final class DunningChurnRepo implements ChurnMetricsRepositoryInterface
{
    public int $riskUpserts = 0;
    public int $riskResolved = 0;

    public function countActiveDemos(): int { return 0; }
    public function countDemosExpiringWithinDays(int $days): int { return 0; }
    public function countOpenRiskSignals(): int { return 0; }
    public function getLatestChurnSnapshot(): ?array { return null; }
    public function findOpenRiskSignals(int $limit = 5): array { return []; }
    public function upsertRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType, string $severity = 'medium', ?array $payload = null): void
    {
        $this->riskUpserts++;
    }
    public function resolveOpenRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType): void
    {
        $this->riskResolved++;
    }
    public function saveChurnSnapshot(array $data): void {}
    public function findRecentlyProvisioned(int $hours = 24): array { return []; }
    public function countDemosStarted(int $year, int $month): int { return 0; }
    public function countDemosConverted(int $year, int $month): int { return 0; }
    public function countClientsStart(int $year, int $month): int { return 0; }
    public function countClientsLost(int $year, int $month): int { return 0; }
    public function countActiveByUsage(int $year, int $month): int { return 0; }
}

final class DunningMailer implements MailerInterface
{
    /** @var list<MensajeCorreo> */
    public array $sent = [];
    public function enviar(MensajeCorreo $mensaje): void { $this->sent[] = $mensaje; }
}

final class DunningPlantillas implements PlantillaRepositoryInterface
{
    public function findActiveByClave(string $clave): ?array
    {
        return [
            'id' => 1,
            'clave' => $clave,
            'asunto' => 'Test',
            'cuerpo' => '{{nombre}} {{retry_url}}',
            'activo' => 1,
        ];
    }
}

final class DunningApiTransport implements LebytekApiTransport
{
    /** @var list<array{method:string,url:string}> */
    public array $calls = [];
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url');
        return ['status' => 200, 'body' => '{"commercialStatus":"cancelled","tokensRevoked":1}', 'error' => ''];
    }
}

test('payment_failed pone past_due sin cancelled_at', function (): void {
    $_ENV['APP_URL'] = 'https://lebytek.com';
    $memberships = new DunningMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 5,
        'api_tenant_public_id' => '01TENANT',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
        'stripe_subscription_id' => 'sub_1',
    ]);
    $leads = new DunningLeadRepo();
    $leads->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'ana@test.com', 'cancelled_at' => null];
    $mailer = new DunningMailer();
    $svc = new StartMembershipGraceService($memberships, $leads, new DunningChurnRepo(), new MarketingMailRenderer(new DunningPlantillas(), $mailer));
    $svc->handle($memberships->rows[$id], 'evt_1');
    assert_same('past_due', $memberships->rows[$id]['status']);
    assert_true($leads->rows[5]['cancelled_at'] === null);
    assert_same(1, count($mailer->sent));
});

test('segundo payment_failed no extiende gracia', function (): void {
    $_ENV['APP_URL'] = 'https://lebytek.com';
    $memberships = new DunningMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 5,
        'api_tenant_public_id' => '01TENANT',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
    ]);
    $leads = new DunningLeadRepo();
    $leads->rows[5] = ['id' => 5, 'nombre' => 'Ana', 'email' => 'ana@test.com'];
    $svc = new StartMembershipGraceService($memberships, $leads, new DunningChurnRepo(), new MarketingMailRenderer(new DunningPlantillas(), new DunningMailer()));
    $svc->handle($memberships->rows[$id], 'evt_1');
    $firstEnd = $memberships->rows[$id]['grace_ends_at'];
    $svc->handle($memberships->rows[$id], 'evt_2');
    assert_same(1, $memberships->markPastDueCalls);
    assert_same($firstEnd, $memberships->rows[$id]['grace_ends_at']);
});

test('expire tras 48h soft-cancel y cancelled_at', function (): void {
    $_ENV['APP_URL'] = 'https://lebytek.com';
    $memberships = new DunningMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 9,
        'api_tenant_public_id' => '01TENANT2',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
    ]);
    $memberships->markPastDue($id, new DateTimeImmutable('-1 hour'), hash('sha256', 'retry'));
    $leads = new DunningLeadRepo();
    $leads->rows[9] = ['id' => 9, 'nombre' => 'Bob', 'email' => 'bob@test.com', 'cancelled_at' => null];
    $transport = new DunningApiTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $mailer = new DunningMailer();
    $expire = new ExpireMembershipGraceService($memberships, $leads, $api, new DunningChurnRepo(), new MarketingMailRenderer(new DunningPlantillas(), $mailer));
    $count = $expire->expireDue(new DateTimeImmutable('now'));
    assert_same(1, $count);
    assert_same('cancelled', $memberships->rows[$id]['status']);
    assert_true($leads->rows[9]['cancelled_at'] !== null);
    assert_same(1, count($transport->calls));
    assert_same(1, count($mailer->sent));
});

test('recover past_due clears grace without cancelled_at', function (): void {
    $memberships = new DunningMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 3,
        'api_tenant_public_id' => '01TENANT3',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
        'status' => 'past_due',
    ]);
    $memberships->markPastDue($id, new DateTimeImmutable('+24 hours'), hash('sha256', 'x'));
    $leads = new DunningLeadRepo();
    $leads->rows[3] = ['id' => 3, 'cancelled_at' => null];
    $churn = new DunningChurnRepo();
    $recover = new RecoverMembershipPaymentService(
        $memberships,
        $leads,
        $churn,
        new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new DunningApiTransport()),
        new PaymentGatewayRegistry([]),
    );
    $event = new PaymentEvent(
        PaymentEventType::InvoicePaid,
        'evt_recover',
        '',
        Money::fromMajor(2199, 'mxn'),
        'paid',
        'sub_x',
        'cus_x',
    );
    $recover->recoverAfterSuccessfulPayment($memberships->rows[$id], $event);
    assert_same('active', $memberships->rows[$id]['status']);
    assert_true(! isset($memberships->rows[$id]['grace_ends_at']));
    assert_true($leads->rows[3]['cancelled_at'] === null);
    assert_same(1, $churn->riskResolved);
});

test('retry checkout no mueve grace_ends_at', function (): void {
    $memberships = new DunningMembershipRepo();
    $id = $memberships->upsertFromActivation([
        'lead_id' => 4,
        'api_tenant_public_id' => '01TENANT4',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
    ]);
    $rawToken = bin2hex(random_bytes(32));
    $memberships->markPastDue($id, new DateTimeImmutable('+40 hours'), hash('sha256', $rawToken));
    $firstEnd = $memberships->rows[$id]['grace_ends_at'];
    $recover = new RecoverMembershipPaymentService(
        $memberships,
        new DunningLeadRepo(),
        new DunningChurnRepo(),
        new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, new DunningApiTransport()),
        new PaymentGatewayRegistry([]),
    );
    $found = $recover->findByRetryToken($rawToken);
    assert_true($found !== null);
    try {
        $recover->checkoutUrlForMembresia($found, '/membresia/pago/exito', '/membresia/pago/cancelado');
    } catch (\Throwable) {
        // Sin gateway Stripe en test; lo relevante es que no se tocó la gracia.
    }
    assert_same($firstEnd, $memberships->rows[$id]['grace_ends_at']);
    assert_same('past_due', $memberships->rows[$id]['status']);
});
