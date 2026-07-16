<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface MembershipRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function upsertFromActivation(array $data): int;

    /** @return array<string, mixed>|null */
    public function findByTenantPublicId(string $tenantPublicId): ?array;

    /** @return array<string, mixed>|null */
    public function findByStripeSubscriptionId(string $subscriptionId): ?array;

    /** @return array<string, mixed>|null */
    public function findByRetryTokenHash(string $hash): ?array;

    /** @return array<string, mixed>|null */
    public function findByReactivationTokenHash(string $hash): ?array;

    public function markPastDue(int $id, \DateTimeInterface $graceEndsAt, string $retryTokenHash): void;

    public function clearGrace(int $id): void;

    public function markCancelled(int $id, string $reactivationTokenHash): void;

    public function markActive(int $id, ?\DateTimeInterface $periodEnd = null): void;

    /** @return list<array<string, mixed>> */
    public function findGraceExpired(\DateTimeInterface $now): array;

    public function bindStripeSubscription(int $id, ?string $customerId, ?string $subscriptionId): void;
}
