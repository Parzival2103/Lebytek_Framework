<?php

declare(strict_types=1);

use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Infrastructure\Marketing\PdoMembershipRepository;

final class InMemoryMembershipRepository implements MembershipRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];
    private int $nextId = 1;

    public function upsertFromActivation(array $data): int
    {
        $tenant = (string) ($data['api_tenant_public_id'] ?? '');
        foreach ($this->rows as $id => $row) {
            if ($row['api_tenant_public_id'] === $tenant) {
                $this->rows[$id] = array_merge($row, $data, ['id' => $id, 'status' => $data['status'] ?? 'active']);

                return $id;
            }
        }
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, ['id' => $id, 'status' => $data['status'] ?? 'active']);

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
        if ($periodEnd !== null) {
            $this->rows[$id]['current_period_end'] = $periodEnd->format('Y-m-d H:i:s');
        }
        unset($this->rows[$id]['cancelled_at'], $this->rows[$id]['reactivation_token_hash']);
    }

    public function findGraceExpired(\DateTimeInterface $now): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if (($row['status'] ?? '') !== 'past_due') {
                continue;
            }
            $ends = $row['grace_ends_at'] ?? null;
            if ($ends !== null && $ends <= $now->format('Y-m-d H:i:s')) {
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

test('membership repo upsert and grace lifecycle on fake', function (): void {
    $repo = new InMemoryMembershipRepository();
    $id = $repo->upsertFromActivation([
        'api_tenant_public_id' => '01JTENANT0000000000000001',
        'plan_slug' => 'starter',
        'ciclo' => 'monthly',
    ]);
    $graceEnd = new DateTimeImmutable('+48 hours');
    $repo->markPastDue($id, $graceEnd, hash('sha256', 'retry'));
    $row = $repo->findByTenantPublicId('01JTENANT0000000000000001');
    assert_same('past_due', $row['status']);
    $repo->clearGrace($id);
    assert_same('active', $repo->findByTenantPublicId('01JTENANT0000000000000001')['status']);
});

test('PdoMembershipRepository implements contract', function (): void {
    $repo = new PdoMembershipRepository();
    assert_true($repo instanceof MembershipRepositoryInterface);
});

test('marketing module registers membresias migration', function (): void {
    $manifest = require ROOT_PATH.'/config/modules/marketing.php';
    assert_true(in_array('20260715210000_mkt_membresias.sql', $manifest['migraciones'] ?? [], true));
});
