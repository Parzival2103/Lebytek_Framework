<?php

declare(strict_types=1);

/**
 * Permisos referenciados en RbacMiddleware de routes/web.php.
 * Mantener alineado al cambiar rutas (ver docs/core/auth_rbac_seguridad_v0.1.md).
 *
 * Slugs dinámicos CRUD/calendario viven en crud_router (CrudRbacMiddleware) y
 * se cruzan vía config/cruds/*.json — no listarlos en middleware[].
 */
return [
    'middleware' => [
        'dashboard.ver',
        'administracion.ver',
        'usuarios.gestionar',
        'roles.gestionar',
        'sistema.ver',
        'pdf_kit.ver',
        'reportes.ver',
        'reportes.crear',
        'reportes.editar',
        'reportes.eliminar',
        'reportes.generar',
    ],
    'crud_router' => [
        'middleware_class' => 'Lebytek\\Framework\\Presentation\\Middlewares\\CrudRbacMiddleware',
        'routes' => ['/admin/crud/{resource}*', '/admin/calendario/{key}*'],
        'permission_source' => 'config/cruds/{resource}.json → permission_prefix + acción por URI',
        'note' => 'Slugs dinámicos no listados en middleware[] — el informe los cruza vía config/cruds/*.json',
    ],
];
