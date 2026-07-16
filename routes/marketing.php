<?php

// routes/marketing.php
// Rutas del módulo Marketing. Incluido condicionalmente desde routes/web.php
// solo cuando vertical.modules.marketing === true. Tiene $router en scope.

use App\Presentation\Controllers\Publico\LandingController;
use App\Presentation\Controllers\Publico\LandingMetricsController;
use App\Presentation\Controllers\Publico\LeadController;
use App\Presentation\Controllers\Publico\LeadEmailVerificationController;
use App\Presentation\Controllers\Publico\PortalClienteController;
use App\Presentation\Controllers\Publico\CompraController;
use App\Presentation\Controllers\Publico\StripeWebhookController;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;

// Raíz pública: con el módulo activo, "/" sirve la landing (no el login).
$router->get('/', [LandingController::class, 'index']);

// Colector first-party de métricas de landing. SIN CsrfMiddleware: primer POST
// público abierto del proyecto (endurecido con rate limit sys_kv + validación
// de payload en el propio use case — Anti-deuda §C).
$router->post('/marketing/collect', [LandingMetricsController::class, 'collect']);

// Captación de leads (POST público con CSRF).
$router->post('/lead', [LeadController::class, 'capturar'], [CsrfMiddleware::class]);

// Verificación de correo del lead (código de un solo uso, 24h, 5 intentos).
$router->get('/verificar-demo/{token}', [LeadEmailVerificationController::class, 'show']);
$router->post('/verificar-demo/{token}', [LeadEmailVerificationController::class, 'submit'], [CsrfMiddleware::class]);

// Portal cliente genérico (magic-link).
$router->get('/portal', [PortalClienteController::class, 'entrar']);

// Compra de membresía (transferencia bancaria v1).
$router->get('/comprar/{slug}', [CompraController::class, 'show']);
$router->post('/comprar/{slug}', [CompraController::class, 'submit'], [CsrfMiddleware::class]);
$router->get('/comprar/orden/{publicId}/transferencia', [CompraController::class, 'transferencia']);
$router->get('/comprar/orden/{publicId}/pago/exito', [CompraController::class, 'pagoExito']);
$router->get('/comprar/orden/{publicId}/pago/cancelado', [CompraController::class, 'pagoCancelado']);

// Stripe signs the raw request body; this endpoint must remain outside CSRF middleware.
if ((bool) Config::get('vertical.modules.payments', false)) {
    $router->post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
}
