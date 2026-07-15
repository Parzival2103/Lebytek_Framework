<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface ChurnMetricsRepositoryInterface
{
    public function countActiveDemos(): int;

    public function countDemosExpiringWithinDays(int $days): int;

    public function countOpenRiskSignals(): int;

    /** @return array<string, mixed>|null */
    public function getLatestChurnSnapshot(): ?array;

    /** @return list<array<string, mixed>> */
    public function findOpenRiskSignals(int $limit = 5): array;

    public function upsertRiskSignal(
        ?int $leadId,
        ?string $tenantPublicId,
        string $signalType,
        string $severity = 'medium',
        ?array $payload = null,
    ): void;

    /** @param array<string, mixed> $data */
    public function saveChurnSnapshot(array $data): void;

    /** @return list<array<string, mixed>> */
    public function findRecentlyProvisioned(int $hours = 24): array;
}
