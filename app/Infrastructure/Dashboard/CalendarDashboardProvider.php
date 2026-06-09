<?php

declare(strict_types=1);

namespace App\Infrastructure\Dashboard;

use App\Application\Services\CalendarConfigLoader;
use App\Domain\Dashboard\DashboardBuildContext;
use App\Domain\Dashboard\DashboardContribution;
use App\Domain\Interfaces\DashboardContributionProviderInterface;

/**
 * Aporta un widget mini-calendario (solo vista) por cada calendario con
 * `dashboard_widget=true` que el usuario pueda ver. No añade KPIs ni actividad.
 */
final class CalendarDashboardProvider implements DashboardContributionProviderInterface
{
    public function __construct(
        private readonly CalendarConfigLoader $loader,
    ) {}

    public function priority(): int
    {
        return 60;
    }

    public function contribute(DashboardBuildContext $context): DashboardContribution
    {
        $widgets = [];

        foreach (array_keys($this->loader->listCalendars()) as $key) {
            try {
                $def = $this->loader->load($key);
                if (!$def->dashboardWidget()) {
                    continue;
                }
                $prefix = $this->loader->crudDefinition($def->resource())->permissionPrefix();
                if (!$context->tienePermiso($prefix . '.ver')) {
                    continue;
                }
                $widgets[] = [
                    'partial' => 'dashboard/calendar_mini',
                    'data' => [
                        'key'     => $def->key(),
                        'title'   => $def->title(),
                        'icon'    => $def->icon(),
                        'url'     => '/admin/calendario/' . $def->key(),
                        'feedUrl' => '/admin/calendario/' . $def->key() . '/eventos',
                    ],
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        if ($widgets === []) {
            return DashboardContribution::vacia();
        }

        return new DashboardContribution(
            kpis: [],
            activityItems: [],
            quickAccess: [],
            statusBlock: null,
            widgets: $widgets,
        );
    }
}
