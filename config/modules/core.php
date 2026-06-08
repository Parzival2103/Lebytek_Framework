<?php

declare(strict_types=1);

// Manifiesto del módulo core. Reclama el schema base + toda la plataforma
// (auth, menú, ajustes). Obligatorio: ningún deploy existe sin core.
return [
    'clave'       => 'core',
    'nombre'      => 'Núcleo de plataforma',
    'descripcion' => 'Autenticación, RBAC, menú dinámico, ajustes y configuración base.',
    'version'     => '1.0.0',
    'obligatorio' => true,
    'requiere'    => [],
    'migraciones' => [
        '20260427120000_core_menu_items.sql',
        '20260428133000_crud_demo_menu_parent_perm_null.sql',
        '20260502120000_menu_rbac_granular_admin_subitems.sql',
        '20260502150000_auth_permisos_dom_clientes.sql',
        '20260503100000_deprecate_legacy_domain_permissions_and_menus.sql',
    ],
    'seeds' => [
        '010_auth_permisos.sql',
        '015_core_menu_items.sql',
        '020_auth_roles.sql',
        '025_auth_roles_permisos.sql',
        '030_auth_usuario_admin.sql',
        '035_cfg_configuraciones.sql',
    ],
    'cruds'     => [],
    'permisos'  => [
        'administracion.ver', 'usuarios.gestionar', 'roles.gestionar',
        'bitacora.ver', 'dashboard.ver', 'sistema.ver',
    ],
    'menu'      => ['dashboard', 'administracion'],
    'providers' => [],
];
