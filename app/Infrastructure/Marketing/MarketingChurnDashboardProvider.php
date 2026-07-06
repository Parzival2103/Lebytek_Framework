<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use Lebytek\Framework\Domain\Dashboard\DashboardBuildContext;
use Lebytek\Framework\Domain\Dashboard\DashboardContribution;
use Lebytek\Framework\Domain\Interfaces\DashboardContributionProviderInterface;

final class MarketingChurnDashboardProvider implements DashboardContributionProviderInterface
{
    public function __construct(
        private readonly ChurnMetricsRepositoryInterface $metrics,
    ) {}

    public function priority(): int
    {
        return 50;
    }

    public function contribute(DashboardBuildContext $context): DashboardContribution
    {
        if (! $context->tienePermiso('marketing.leads')) {
            return DashboardContribution::vacia();
        }

        $snapshot = $this->metrics->getLatestChurnSnapshot();
        $churnLabel = '—';
        if (is_array($snapshot)) {
            $churnLabel = number_format((float) ($snapshot['churn_rate_pct'] ?? 0), 1).'%';
        }

        $conversionLabel = '—';
        if (is_array($snapshot) && $snapshot['demo_conversion_pct'] !== null) {
            $conversionLabel = number_format((float) $snapshot['demo_conversion_pct'], 1).'%';
        }

        $kpis = [
            [
                'label'       => 'Demos activas',
                'value'       => (string) $this->metrics->countActiveDemos(),
                'icon'        => 'bi-cloud-check',
                'color'       => 'primary',
                'url'         => '/admin/crud/mkt_leads?estado=demo_enviada',
                'description' => 'Leads con demo en curso',
            ],
            [
                'label'       => 'Por vencer (7d)',
                'value'       => (string) $this->metrics->countDemosExpiringWithinDays(7),
                'icon'        => 'bi-hourglass-split',
                'color'       => 'warning',
                'url'         => '/admin/crud/mkt_leads?estado=demo_enviada',
                'description' => 'Demos que expiran pronto',
            ],
            [
                'label'       => 'En riesgo',
                'value'       => (string) $this->metrics->countOpenRiskSignals(),
                'icon'        => 'bi-exclamation-triangle',
                'color'       => 'danger',
                'url'         => '#',
                'description' => 'Señales abiertas rep_risk',
            ],
            [
                'label'       => 'Churn mes ant.',
                'value'       => $churnLabel,
                'icon'        => 'bi-graph-down-arrow',
                'color'       => 'secondary',
                'url'         => '#',
                'description' => 'Último snapshot mensual',
            ],
            [
                'label'       => 'Conv. demo→pago',
                'value'       => $conversionLabel,
                'icon'        => 'bi-arrow-up-right-circle',
                'color'       => 'success',
                'url'         => '#',
                'description' => 'Último periodo calculado',
            ],
        ];

        $activity = [];
        foreach ($this->metrics->findRecentlyProvisioned(24) as $lead) {
            $activity[] = [
                'icon' => 'bi-envelope-check',
                'text' => 'Demo provisionada: '.(string) ($lead['nombre'] ?? 'Lead'),
                'meta' => (string) ($lead['email'] ?? ''),
            ];
        }

        $quick = [
            ['url' => '/admin/crud/mkt_leads', 'icon' => 'bi-people', 'label' => 'Leads'],
            ['url' => '/admin/crud/mkt_leads?estado=demo_enviada', 'icon' => 'bi-cloud', 'label' => 'Demos activas'],
        ];

        $openSignals = $this->metrics->findOpenRiskSignals(5);
        $widgets = [];
        if ($openSignals !== []) {
            $widgets[] = [
                'partial' => 'dashboard/mkt_at_risk_list',
                'data'    => ['signals' => $openSignals],
            ];
        }

        return new DashboardContribution(
            kpis: $kpis,
            activityItems: $activity,
            quickAccess: $quick,
            statusBlock: [
                'badge'     => $this->metrics->countOpenRiskSignals() > 0 ? 'Atención' : 'OK',
                'badgeTone' => $this->metrics->countOpenRiskSignals() > 0 ? 'warning' : 'success',
                'lines'     => [
                    ['text' => 'Métricas de retención y demos WhatsApp API.', 'tone' => 'muted'],
                ],
            ],
            widgets: $widgets,
        );
    }
}
