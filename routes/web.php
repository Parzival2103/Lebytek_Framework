<?php

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\Admin\DashboardController;
use App\Presentation\Controllers\Admin\UsuariosController;
use App\Presentation\Controllers\Admin\RolesController;
use App\Presentation\Controllers\Admin\PermisosController;
use App\Presentation\Controllers\Admin\AjustesController;
use App\Presentation\Controllers\Admin\CrudController;
use App\Presentation\Controllers\PwaController;
use App\Presentation\Middlewares\AuthMiddleware;
use App\Presentation\Middlewares\CsrfMiddleware;
use App\Presentation\Middlewares\RbacMiddleware;

/*
|--------------------------------------------------------------------------
| Rutas web (HTML/sesión)
|--------------------------------------------------------------------------
*/

$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);

$router->get('/login',  [AuthController::class, 'showLogin']);
$router->get('/',       [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

$router->group([
    'prefix'      => '/admin',
    'middlewares' => [AuthMiddleware::class],
], function ($router) {

    $rbacDashboard = [new RbacMiddleware('dashboard.ver')];
    $rbacAjustes   = [new RbacMiddleware('administracion.ver')];
    $rbacUsuarios  = [new RbacMiddleware('usuarios.gestionar')];
    $rbacRoles     = [new RbacMiddleware('roles.gestionar')];
    /*
     * permisos.gestionar no existe en seeds/base; se usa administracion.ver
     * hasta definir slug dedicado (ver docs/audits/correccion_alineacion_modulos_v0.1.md).
     */
    $rbacPermisos  = [new RbacMiddleware('administracion.ver')];

    $router->get('/dashboard', [DashboardController::class, 'index'], $rbacDashboard);

    $router->get('/ajustes',              [AjustesController::class, 'index'], $rbacAjustes);
    $router->post('/ajustes',              [AjustesController::class, 'guardar'],     array_merge($rbacAjustes, [CsrfMiddleware::class]));
    $router->post('/ajustes/toggle-tema', [AjustesController::class, 'toggleTema'], array_merge($rbacAjustes, [CsrfMiddleware::class]));

    $router->group([
        'prefix' => '/administracion',
    ], function ($router) use ($rbacUsuarios, $rbacRoles, $rbacPermisos) {

        $router->get('/usuarios',                [UsuariosController::class, 'index'], $rbacUsuarios);
        $router->get('/usuarios/crear',          [UsuariosController::class, 'crear'], $rbacUsuarios);
        $router->post('/usuarios',               [UsuariosController::class, 'guardar'],    array_merge($rbacUsuarios, [CsrfMiddleware::class]));
        $router->get('/usuarios/{id}/editar',    [UsuariosController::class, 'editar'], $rbacUsuarios);
        $router->put('/usuarios/{id}',           [UsuariosController::class, 'actualizar'], array_merge($rbacUsuarios, [CsrfMiddleware::class]));
        $router->delete('/usuarios/{id}',        [UsuariosController::class, 'eliminar'],   array_merge($rbacUsuarios, [CsrfMiddleware::class]));

        $router->get('/roles',                [RolesController::class, 'index'], $rbacRoles);
        $router->get('/roles/crear',          [RolesController::class, 'crear'], $rbacRoles);
        $router->post('/roles',               [RolesController::class, 'guardar'],    array_merge($rbacRoles, [CsrfMiddleware::class]));
        $router->get('/roles/{id}/editar',    [RolesController::class, 'editar'], $rbacRoles);
        $router->put('/roles/{id}',           [RolesController::class, 'actualizar'], array_merge($rbacRoles, [CsrfMiddleware::class]));
        $router->delete('/roles/{id}',        [RolesController::class, 'eliminar'],   array_merge($rbacRoles, [CsrfMiddleware::class]));

        $router->get('/permisos',                [PermisosController::class, 'index'], $rbacPermisos);
        $router->get('/permisos/crear',          [PermisosController::class, 'crear'], $rbacPermisos);
        $router->post('/permisos',               [PermisosController::class, 'guardar'],    array_merge($rbacPermisos, [CsrfMiddleware::class]));
        $router->get('/permisos/{id}/editar',    [PermisosController::class, 'editar'], $rbacPermisos);
        $router->put('/permisos/{id}',           [PermisosController::class, 'actualizar'], array_merge($rbacPermisos, [CsrfMiddleware::class]));
        $router->delete('/permisos/{id}',        [PermisosController::class, 'eliminar'],   array_merge($rbacPermisos, [CsrfMiddleware::class]));
    });

    $router->get('/crud/{resource}',                  [CrudController::class, 'index']);
    $router->get('/crud/{resource}/crear',            [CrudController::class, 'create']);
    $router->post('/crud/{resource}',                 [CrudController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/crud/{resource}/{id}/editar',      [CrudController::class, 'edit']);
    $router->post('/crud/{resource}/{id}',            [CrudController::class, 'update'], [CsrfMiddleware::class]);
    $router->get('/crud/{resource}/{id}',             [CrudController::class, 'show']);
    $router->post('/crud/{resource}/{id}/eliminar',   [CrudController::class, 'delete'], [CsrfMiddleware::class]);
});
