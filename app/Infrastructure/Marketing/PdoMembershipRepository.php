<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoMembershipRepository implements MembershipRepositoryInterface
{
    public function upsertFromActivation(array $data): int
    {
        $pdo = Connection::getInstance();
        $tenantId = (string) ($data['api_tenant_public_id'] ?? '');
        if ($tenantId === '') {
            throw new \InvalidArgumentException('api_tenant_public_id is required');
        }

        $existing = $this->findByTenantPublicId($tenantId);
        if ($existing !== null) {
            $stmt = $pdo->prepare(
                'UPDATE dom_mkt_membresias SET
                    lead_id = COALESCE(:lead_id, lead_id),
                    plan_slug = :plan_slug,
                    ciclo = :ciclo,
                    status = :status,
                    stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
                    stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
                    current_period_end = COALESCE(:current_period_end, current_period_end),
                    grace_started_at = NULL,
                    grace_ends_at = NULL,
                    cancelled_at = NULL,
                    reactivation_token_hash = NULL,
                    retry_token_hash = NULL,
                    retry_expires_at = NULL,
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => (int) $existing['id'],
                'lead_id' => $data['lead_id'] ?? null,
                'plan_slug' => (string) ($data['plan_slug'] ?? ''),
                'ciclo' => (string) ($data['ciclo'] ?? 'monthly'),
                'status' => (string) ($data['status'] ?? 'active'),
                'stripe_customer_id' => $data['stripe_customer_id'] ?? null,
                'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
                'current_period_end' => $data['current_period_end'] ?? null,
            ]);

            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_membresias (
                lead_id, api_tenant_public_id, plan_slug, ciclo, status,
                stripe_customer_id, stripe_subscription_id, current_period_end
             ) VALUES (
                :lead_id, :tenant_id, :plan_slug, :ciclo, :status,
                :stripe_customer_id, :stripe_subscription_id, :current_period_end
             )'
        );
        $stmt->execute([
            'lead_id' => $data['lead_id'] ?? null,
            'tenant_id' => $tenantId,
            'plan_slug' => (string) ($data['plan_slug'] ?? ''),
            'ciclo' => (string) ($data['ciclo'] ?? 'monthly'),
            'status' => (string) ($data['status'] ?? 'active'),
            'stripe_customer_id' => $data['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
            'current_period_end' => $data['current_period_end'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function findByTenantPublicId(string $tenantPublicId): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_membresias WHERE api_tenant_public_id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantPublicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByStripeSubscriptionId(string $subscriptionId): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_membresias WHERE stripe_subscription_id = :sub LIMIT 1');
        $stmt->execute(['sub' => $subscriptionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByRetryTokenHash(string $hash): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM dom_mkt_membresias
             WHERE retry_token_hash = :hash
               AND (retry_expires_at IS NULL OR retry_expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByReactivationTokenHash(string $hash): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_membresias WHERE reactivation_token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markPastDue(int $id, \DateTimeInterface $graceEndsAt, string $retryTokenHash): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_membresias SET
                status = :status,
                grace_started_at = COALESCE(grace_started_at, NOW()),
                grace_ends_at = :grace_ends_at,
                retry_token_hash = :retry_hash,
                retry_expires_at = :grace_ends_at,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'past_due',
            'grace_ends_at' => $graceEndsAt->format('Y-m-d H:i:s'),
            'retry_hash' => $retryTokenHash,
        ]);
    }

    public function clearGrace(int $id): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_membresias SET
                status = :status,
                grace_started_at = NULL,
                grace_ends_at = NULL,
                retry_token_hash = NULL,
                retry_expires_at = NULL,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => 'active']);
    }

    public function markCancelled(int $id, string $reactivationTokenHash): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_membresias SET
                status = :status,
                cancelled_at = COALESCE(cancelled_at, NOW()),
                reactivation_token_hash = :react_hash,
                grace_started_at = NULL,
                grace_ends_at = NULL,
                retry_token_hash = NULL,
                retry_expires_at = NULL,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'cancelled',
            'react_hash' => $reactivationTokenHash,
        ]);
    }

    public function markActive(int $id, ?\DateTimeInterface $periodEnd = null): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_membresias SET
                status = :status,
                current_period_end = COALESCE(:period_end, current_period_end),
                cancelled_at = NULL,
                reactivation_token_hash = NULL,
                grace_started_at = NULL,
                grace_ends_at = NULL,
                retry_token_hash = NULL,
                retry_expires_at = NULL,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'active',
            'period_end' => $periodEnd?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findGraceExpired(\DateTimeInterface $now): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM dom_mkt_membresias
             WHERE status = :status
               AND grace_ends_at IS NOT NULL
               AND grace_ends_at <= :now'
        );
        $stmt->execute([
            'status' => 'past_due',
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function bindStripeSubscription(int $id, ?string $customerId, ?string $subscriptionId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_membresias SET
                stripe_customer_id = COALESCE(:customer_id, stripe_customer_id),
                stripe_subscription_id = COALESCE(:subscription_id, stripe_subscription_id),
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
        ]);
    }
}
