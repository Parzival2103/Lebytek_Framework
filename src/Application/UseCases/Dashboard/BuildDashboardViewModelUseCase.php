<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Dashboard;

use Lebytek\Framework\Application\DTO\Dashboard\DashboardViewModel;
use Lebytek\Framework\Domain\Dashboard\DashboardBuildContext;
use Lebytek\Framework\Domain\Interfaces\DashboardContributionProviderInterface;

/**
 * Fusiona contribuciones de proveedores en el orden de registro (config/dashboard.php).
 */
final class BuildDashboardViewModelUseCase
{
    /**
     * @param iterable<DashboardContributionProviderInterface> $providers Orden determinista
     */
    public function __construct(
        private readonly iterable $providers,
    ) {}

    public function execute(DashboardBuildContext $context): DashboardViewModel
    {
        $kpis = [];
        $activity = [];
        $quick = [];
        $statusBadge = '';
        $statusBadgeTone = 'success';
        $statusLinesMerged = [];
        $widgets = [];

        /** @var list<DashboardContributionProviderInterface> $sorted */
        $sorted = [...$this->providers];
        usort($sorted, static fn(DashboardContributionProviderInterface $a, DashboardContributionProviderInterface $b): int =>
            $a->priority() <=> $b->priority());

        foreach ($sorted as $provider) {
            $c = $provider->contribute($context);

            foreach ($c->kpis as $row) {
                $kpis[] = $row;
            }
            foreach ($c->activityItems as $row) {
                $activity[] = $row;
            }
            foreach ($c->quickAccess as $row) {
                $quick[] = $row;
            }

            if ($c->statusBlock !== null && $c->statusBlock !== []) {
                if (!empty($c->statusBlock['badge'])) {
                    $statusBadge = (string) $c->statusBlock['badge'];
                }
                if (!empty($c->statusBlock['badgeTone'])) {
                    $statusBadgeTone = (string) $c->statusBlock['badgeTone'];
                }
                foreach ($c->statusBlock['lines'] ?? [] as $ln) {
                    $statusLinesMerged[] = $ln;
                }
            }

            foreach ($c->widgets as $widget) {
                $widgets[] = $widget;
            }
        }

        return new DashboardViewModel(
            pageTitle:           'Dashboard',
            kpis:                $kpis,
            activityItems:       $activity,
            quickAccessItems:    $quick,
            sections:            [
                'activityTitle'       => 'Actividad reciente',
                'activityPlaceholder' => $activity === []
                    ? 'Sin actividad reciente registrada por los módulos. Cuando otros módulos aporten líneas aparecerán aquí.'
                    : '',
                'statusTitle'         => 'Estado del sistema',
                'badge'               => $statusBadge !== '' ? $statusBadge : 'OK',
                'badgeTone'           => $statusBadgeTone !== '' ? $statusBadgeTone : 'success',
                'statusLines'         => $statusLinesMerged,
            ],
            widgets:             $widgets,
        );
    }
}
