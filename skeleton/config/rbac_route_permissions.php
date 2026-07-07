<?php

declare(strict_types=1);

/**
 * Permisos referenciados en RbacMiddleware de routes/web.php.
 * Mantener alineado al cambiar rutas (ver docs/core/auth_rbac_seguridad_v0.1.md).
 */
return [
    'middleware' => [
        'dashboard.ver',
        'administracion.ver',
        'usuarios.gestionar',
        'roles.gestionar',
    ],
];
