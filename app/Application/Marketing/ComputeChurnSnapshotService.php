<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;

final class ComputeChurnSnapshotService
{
    public function __construct(
        private readonly ChurnMetricsRepositoryInterface $metrics,
    ) {}

    /** @return array<string, mixed> */
    public function computeFor(int $year, int $month): array
    {
        $demosStarted = $this->metrics->countDemosStarted($year, $month);
        $demosConverted = $this->metrics->countDemosConverted($year, $month);
        $clientsStart = $this->metrics->countClientsStart($year, $month);
        $clientsLost = $this->metrics->countClientsLost($year, $month);
        $activeByUsage = $this->metrics->countActiveByUsage($year, $month);
        $atRisk = $this->metrics->countOpenRiskSignals();

        $demoConversionPct = $demosStarted === 0
            ? null
            : round(100 * $demosConverted / max($demosStarted, 1), 2);

        $churnRatePct = round(100 * $clientsLost / max($clientsStart, 1), 2);

        return [
            'period_year' => $year,
            'period_month' => $month,
            'clients_start' => $clientsStart,
            'clients_lost' => $clientsLost,
            'churn_rate_pct' => $churnRatePct,
            'demos_started' => $demosStarted,
            'demos_converted' => $demosConverted,
            'demo_conversion_pct' => $demoConversionPct,
            'active_by_usage' => $activeByUsage,
            'at_risk_count' => $atRisk,
            'net_new_clients' => max(0, $demosConverted - $clientsLost),
        ];
    }

    /** @return array<string, mixed> */
    public function computeAndSave(int $year, int $month): array
    {
        $snapshot = $this->computeFor($year, $month);
        $this->metrics->saveChurnSnapshot($snapshot);

        return $snapshot;
    }
}
