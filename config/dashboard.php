<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard plataforma — proveedores de contribución
|--------------------------------------------------------------------------
| Orden del array = orden de fusión estable (prioridad numérica menor primero dentro de cada clase).
| Añadir FQCN de implementaciones de DashboardContributionProviderInterface.
*/

return [
    'providers' => [
        \App\Infrastructure\Dashboard\DefaultPlatformDashboardProvider::class,
        \App\Infrastructure\Dashboard\CalendarDashboardProvider::class,
    ],
];
