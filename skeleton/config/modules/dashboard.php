<?php

declare(strict_types=1);

// Manifiesto del módulo dashboard. No posee migraciones propias; declara
// los providers de contribución para inventario en la página de estado.
return [
    'clave'       => 'dashboard',
    'nombre'      => 'Dashboard',
    'descripcion' => 'Panel principal extensible mediante providers de contribución.',
    'version'     => '1.0.0',
    'obligatorio' => false,
    'requiere'    => ['core'],
    'migraciones' => [],
    'seeds'       => [],
    'cruds'       => [],
    'permisos'    => ['dashboard.ver'],
    'menu'        => ['dashboard'],
    'providers'   => [],
];
