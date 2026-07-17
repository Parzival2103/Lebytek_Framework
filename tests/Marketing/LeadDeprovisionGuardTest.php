<?php

declare(strict_types=1);

use App\Application\Marketing\LeadApiDeprovisioningService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiTransport;

final class DepGuardLeads implements LeadRepositoryInterface
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
    public function markCancelled(int $leadId): void {}
    public function clearCancelled(int $leadId): void {}
}

final class DepGuardTransport implements LebytekApiTransport
{
    public int $calls = 0;
    public function execute(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls++;
        return ['status' => 200, 'body' => '{}', 'error' => ''];
    }
}

test('deprovision rechaza lead convertido', function (): void {
    $leads = new DepGuardLeads();
    $leads->rows[1] = [
        'id' => 1,
        'api_tenant_public_id' => '01T',
        'api_instance_public_id' => '01I',
        'api_lifecycle_status' => 'provisioned',
        'converted_at' => '2026-07-15 12:00:00',
        'plan_slug' => 'starter',
    ];
    $transport = new DepGuardTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'tok', 5, 1, $transport);
    $svc = new LeadApiDeprovisioningService($api, $leads);

    $threw = false;
    try {
        $svc->deprovisionLead(1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_true(str_contains($e->getMessage(), 'convertido') || str_contains($e->getMessage(), 'membresía'));
    }
    assert_true($threw);
    assert_same(0, $transport->calls);
});
