<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Dashboard\DashboardBuildContext;
use App\Domain\Dashboard\DashboardContribution;

/**
 * Proveedor de fragmentos para el dashboard plataforma.
 * Los dominios nuevos pueden implementar esta interfaz en Infrastructure y registrar la clase en config/dashboard.php.
 */
interface DashboardContributionProviderInterface
{
    /**
     * Prioridad menor = se fusiona antes (listas KPI, actividad, accesos en ese orden estable).
     */
    public function priority(): int;

    public function contribute(DashboardBuildContext $context): DashboardContribution;
}
