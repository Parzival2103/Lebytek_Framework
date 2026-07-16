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
    'migraciones' => [
        '20260611120000_empresa_nombre_default_framework_lebytek.sql',
        '20260612120000_auth_registro_recuperacion.sql',
        '20260612120000_empresa_mostrar_nombre.sql',
        '20260612130000_auth_login_intentos.sql',
    ],
    'seeds'       => [],
    'cruds'       => [],
    'permisos'    => [
        'administracion.ver', 'usuarios.gestionar', 'roles.gestionar',
        'bitacora.ver', 'dashboard.ver', 'sistema.ver',
    ],
    'menu'      => ['dashboard', 'administracion'],
    'providers' => [],
];
