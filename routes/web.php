<?php

use Lebytek\Framework\Presentation\Controllers\AuthController;
use Lebytek\Framework\Presentation\Controllers\RegistroController;
use Lebytek\Framework\Presentation\Controllers\RecuperacionController;
use Lebytek\Framework\Presentation\Controllers\Admin\DashboardController;
use Lebytek\Framework\Presentation\Controllers\Admin\UsuariosController;
use Lebytek\Framework\Presentation\Controllers\Admin\RolesController;
use Lebytek\Framework\Presentation\Controllers\Admin\PermisosController;
use Lebytek\Framework\Presentation\Controllers\Admin\AjustesController;
use Lebytek\Framework\Presentation\Controllers\Admin\PerfilController;
use Lebytek\Framework\Presentation\Controllers\Admin\CrudController;
use Lebytek\Framework\Presentation\Controllers\Admin\CalendarioController;
use Lebytek\Framework\Presentation\Controllers\Admin\PdfKitDemoController;
use Lebytek\Framework\Presentation\Controllers\Admin\ReportesController;
use Lebytek\Framework\Presentation\Controllers\Admin\SistemaEstadoController;
use Lebytek\Framework\Presentation\Controllers\PwaController;
use Lebytek\Framework\Presentation\Middlewares\AuthMiddleware;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;
use Lebytek\Framework\Presentation\Middlewares\RbacMiddleware;

/*
|--------------------------------------------------------------------------
| Rutas web (HTML/sesión)
|--------------------------------------------------------------------------
*/

$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);

$marketingActivo = (bool) \Lebytek\Framework\Kernel\Config\Config::get('vertical.modules.marketing', false);
if ($marketingActivo) {
    require ROOT_PATH . '/routes/marketing.php';
}

$integrationsActivo = (bool) \Lebytek\Framework\Kernel\Config\Config::get('vertical.modules.integrations', false);
if ($integrationsActivo) {
    $router->get('/wa/activar/{token}', [\Lebytek\Framework\Presentation\Controllers\Admin\IntegrationsController::class, 'activar']);
    $router->get('/wa/activar/{token}/estado', [\Lebytek\Framework\Presentation\Controllers\Admin\IntegrationsController::class, 'activarEstado']);
}

$router->get('/login',  [AuthController::class, 'showLogin']);
if (!$marketingActivo) {
    $router->get('/', [AuthController::class, 'showLogin']);
}
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// Registro público y recuperación de contraseña (404 en /registro si registro.habilitado=false)
$router->get('/registro',           [RegistroController::class, 'mostrar']);
$router->post('/registro',          [RegistroController::class, 'registrar'], [CsrfMiddleware::class]);
$router->get('/registro/verificar', [RegistroController::class, 'verificar']);
$router->post('/registro/reenviar', [RegistroController::class, 'reenviar'], [CsrfMiddleware::class]);

$router->get('/recuperar',    [RecuperacionController::class, 'mostrar']);
$router->post('/recuperar',   [RecuperacionController::class, 'solicitar'], [CsrfMiddleware::class]);
$router->get('/restablecer',  [RecuperacionController::class, 'mostrarRestablecer']);
$router->post('/restablecer', [RecuperacionController::class, 'restablecer'], [CsrfMiddleware::class]);

$router->group([
    'prefix'      => '/admin',
    'middlewares' => [AuthMiddleware::class],
], function ($router) use ($integrationsActivo, $marketingActivo) {

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

    $router->get('/sistema/estado', [SistemaEstadoController::class, 'index'], [new RbacMiddleware('sistema.ver')]);

    // Perfil propio: cualquier autenticado (sin RBAC extra); actor = sesión.
    $router->get('/perfil',                       [PerfilController::class, 'index']);
    $router->put('/perfil',                       [PerfilController::class, 'actualizar'],     [CsrfMiddleware::class]);
    $router->post('/perfil/avatar',               [PerfilController::class, 'subirAvatar'],    [CsrfMiddleware::class]);
    $router->post('/perfil/avatar/{id}/actual',   [PerfilController::class, 'fijarAvatar'],    [CsrfMiddleware::class]);
    $router->delete('/perfil/avatar/{id}',        [PerfilController::class, 'eliminarAvatar'], [CsrfMiddleware::class]);
    $router->get('/perfil/avatares',              [PerfilController::class, 'listarAvatares']);

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

        $router->post('/usuarios/{id}/avatar',                [UsuariosController::class, 'subirAvatar'],    array_merge($rbacUsuarios, [CsrfMiddleware::class]));
        $router->post('/usuarios/{id}/avatar/{aid}/actual',   [UsuariosController::class, 'fijarAvatar'],    array_merge($rbacUsuarios, [CsrfMiddleware::class]));
        $router->delete('/usuarios/{id}/avatar/{aid}',        [UsuariosController::class, 'eliminarAvatar'], array_merge($rbacUsuarios, [CsrfMiddleware::class]));
        $router->get('/usuarios/{id}/avatares',               [UsuariosController::class, 'listarAvatares'], $rbacUsuarios);

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
    $router->post('/crud/{resource}/{id}/accion/{action}',   [CrudController::class, 'action'],     [CsrfMiddleware::class]);
    $router->post('/crud/{resource}/accion-masiva/{action}', [CrudController::class, 'bulkAction'], [CsrfMiddleware::class]);

    $router->get('/calendario/{key}',         [CalendarioController::class, 'index']);
    $router->get('/calendario/{key}/eventos', [CalendarioController::class, 'events']);

    $rbacPdfKit = [new RbacMiddleware('pdf_kit.ver')];
    $router->get('/pdf-kit/demo',                    [PdfKitDemoController::class, 'index'], $rbacPdfKit);
    $router->get('/pdf-kit/demo/descargar-reporte',  [PdfKitDemoController::class, 'descargarReporte'], $rbacPdfKit);
    $router->get('/pdf-kit/demo/descargar-completo', [PdfKitDemoController::class, 'descargarCompleto'], $rbacPdfKit);

    $router->get('/reportes',                 [ReportesController::class, 'index'],     [new RbacMiddleware('reportes.ver')]);
    $router->get('/reportes/crear',           [ReportesController::class, 'crear'],     [new RbacMiddleware('reportes.crear')]);
    $router->get('/reportes/documento',       [ReportesController::class, 'documento'], [new RbacMiddleware('reportes.generar')]);
    $router->post('/reportes',                [ReportesController::class, 'guardar'],   [new RbacMiddleware('reportes.crear'), CsrfMiddleware::class]);
    $router->get('/reportes/{id}/editar',     [ReportesController::class, 'editar'],    [new RbacMiddleware('reportes.editar')]);
    $router->post('/reportes/{id}',           [ReportesController::class, 'actualizar'],[new RbacMiddleware('reportes.editar'), CsrfMiddleware::class]);
    $router->post('/reportes/{id}/eliminar',  [ReportesController::class, 'eliminar'],  [new RbacMiddleware('reportes.eliminar'), CsrfMiddleware::class]);
    $router->post('/reportes/{id}/generar',   [ReportesController::class, 'generar'],   [new RbacMiddleware('reportes.generar'), CsrfMiddleware::class]);

    if ($integrationsActivo) {
        require ROOT_PATH . '/routes/integrations.php';
    }

    if ($marketingActivo) {
        require ROOT_PATH . '/routes/marketing_admin.php';
    }
});
