<?php
// routes/waapi_portal.php — Panel cliente waapi (solo lectura; consume api.lebytek.com)

use App\Presentation\Controllers\Publico\WaapiPortalController;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;

$router->get('/', [WaapiPortalController::class, 'landing']);
$router->get('/portal/acceso', [WaapiPortalController::class, 'accesoForm']);
$router->post('/portal/acceso', [WaapiPortalController::class, 'accesoSubmit'], [CsrfMiddleware::class]);
$router->get('/portal/dashboard', [WaapiPortalController::class, 'dashboard']);
$router->get('/portal/qr', [WaapiPortalController::class, 'qr']);
$router->get('/portal/qr/estado', [WaapiPortalController::class, 'qrEstado']);
$router->get('/portal/uso', [WaapiPortalController::class, 'uso']);
$router->post('/portal/logout', [WaapiPortalController::class, 'logout'], [CsrfMiddleware::class]);
