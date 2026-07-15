<?php
// routes/waapi_portal.php — Panel cliente waapi (login + métricas de uso)

use App\Presentation\Controllers\Publico\WaapiPortalController;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;

$router->get('/', [WaapiPortalController::class, 'landing']);
$router->get('/portal/acceso', [WaapiPortalController::class, 'accesoForm']);
$router->post('/portal/acceso', [WaapiPortalController::class, 'accesoSubmit'], [CsrfMiddleware::class]);
$router->get('/portal/dashboard', [WaapiPortalController::class, 'dashboard']);
$router->post('/portal/logout', [WaapiPortalController::class, 'logout'], [CsrfMiddleware::class]);
