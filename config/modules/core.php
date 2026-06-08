<?php

declare(strict_types=1);

// Manifiesto del módulo core. Reclama el schema base + toda la plataforma
// (auth, menú, ajustes). Obligatorio: ningún deploy existe sin core.
// Bootstrap en database/schema/schema.sql (DATOS INICIALES).
return [
    'clave'       => 'core',
    'nombre'      => 'Núcleo de plataforma',
    'descripcion' => 'Autenticación, RBAC, menú dinámico, ajustes y configuración base.',
    'version'     => '1.0.0',
    'obligatorio' => true,
    'requiere'    => [],
    'migraciones' => [],
    'seeds'       => [],
    'cruds'       => [],
    'permisos'    => [
        'administracion.ver', 'usuarios.gestionar', 'roles.gestionar',
        'bitacora.ver', 'dashboard.ver', 'sistema.ver',
    ],
    'menu'      => ['dashboard', 'administracion'],
    'providers' => [],
];
