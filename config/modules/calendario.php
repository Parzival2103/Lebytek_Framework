<?php

declare(strict_types=1);

// Manifiesto del módulo Calendario. Capa de solo-lectura sobre el CRUD Engine:
// renderiza recursos CRUD como vistas de calendario + widget de dashboard.
// Bootstrap (tabla demo, permisos, menú) en schema/modules/calendario.sql.
return [
    'clave'         => 'calendario',
    'nombre'        => 'Calendario',
    'descripcion'   => 'Vistas de calendario (mes/semana/día/tabla) sobre recursos CRUD + widget de dashboard.',
    'version'       => '1.0.0',
    'obligatorio'   => false,
    'requiere'      => ['core', 'crud-engine'],
    'migraciones'   => [],
    'seeds'         => [],
    'bootstrap_sql' => 'database/schema/modules/calendario.sql',
    'cruds'         => ['demo_citas'],
    'calendars'     => ['demo_citas'],
    'permisos'      => [],
    'menu'          => [],
    'providers'     => [\App\Infrastructure\Dashboard\CalendarDashboardProvider::class],
];
