<?php

declare(strict_types=1);

use App\Application\Marketing\ComputeChurnSnapshotService;
use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;

final class SnapshotChurnMetricsFake implements ChurnMetricsRepositoryInterface
{
    public int $demosStarted = 10;
    public int $demosConverted = 2;
    public int $clientsStart = 20;
    public int $clientsLost = 1;
    public int $activeByUsage = 15;
    public int $atRisk = 3;

    /** @var array<string, mixed>|null */
    public ?array $saved = null;

    public function countActiveDemos(): int
    {
        return 0;
    }

    public function countDemosExpiringWithinDays(int $days): int
    {
        return 0;
    }

    public function countOpenRiskSignals(): int
    {
        return $this->atRisk;
    }

    public function getLatestChurnSnapshot(): ?array
    {
        return null;
    }

    public function findOpenRiskSignals(int $limit = 5): array
    {
        return [];
    }

    public function upsertRiskSignal(
        ?int $leadId,
        ?string $tenantPublicId,
        string $signalType,
        string $severity = 'medium',
        ?array $payload = null,
    ): void {
    }

    public function resolveOpenRiskSignal(?int $leadId, ?string $tenantPublicId, string $signalType): void
    {
    }

    public function saveChurnSnapshot(array $data): void
    {
        $this->saved = $data;
    }

    public function findRecentlyProvisioned(int $hours = 24): array
    {
        return [];
    }

    public function countDemosStarted(int $year, int $month): int
    {
        return $this->demosStarted;
    }

    public function countDemosConverted(int $year, int $month): int
    {
        return $this->demosConverted;
    }

    public function countClientsStart(int $year, int $month): int
    {
        return $this->clientsStart;
    }

    public function countClientsLost(int $year, int $month): int
    {
        return $this->clientsLost;
    }

    public function countActiveByUsage(int $year, int $month): int
    {
        return $this->activeByUsage;
    }
}

test('snapshot usa converted_at para conversion y cancelled_at para churn paid', function (): void {
    $fake = new SnapshotChurnMetricsFake();
    $svc = new ComputeChurnSnapshotService($fake);
    $svc->computeAndSave(2026, 6);
    assert_true($fake->saved !== null);
    assert_same(20.0, (float) $fake->saved['demo_conversion_pct']);
    assert_same(5.0, (float) $fake->saved['churn_rate_pct']);
});

test('demo_conversion_pct es null cuando no hubo demos iniciadas', function (): void {
    $fake = new SnapshotChurnMetricsFake();
    $fake->demosStarted = 0;
    $fake->demosConverted = 0;
    $svc = new ComputeChurnSnapshotService($fake);
    $row = $svc->computeFor(2026, 7);
    assert_true($row['demo_conversion_pct'] === null);
});
