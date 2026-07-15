<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard plataforma — proveedores de contribución
|--------------------------------------------------------------------------
| Orden del array = orden de fusión estable (prioridad numérica menor primero dentro de cada clase).
| Añadir FQCN de implementaciones de DashboardContributionProviderInterface.
*/

use Lebytek\Framework\Kernel\Config\Config;

$providers = [
    \Lebytek\Framework\Infrastructure\Dashboard\DefaultPlatformDashboardProvider::class,
    \Lebytek\Framework\Infrastructure\Dashboard\CalendarDashboardProvider::class,
];

if ((bool) Config::get('vertical.modules.marketing', false)) {
    $providers[] = \App\Infrastructure\Marketing\MarketingChurnDashboardProvider::class;
}

return [
    'providers' => $providers,
];
