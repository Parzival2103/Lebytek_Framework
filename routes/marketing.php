<?php

// routes/marketing.php
// Rutas del módulo Marketing. Incluido condicionalmente desde routes/web.php
// solo cuando vertical.modules.marketing === true. Tiene $router en scope.

use App\Presentation\Controllers\Publico\LandingController;
use App\Presentation\Controllers\Publico\LeadController;
use App\Presentation\Controllers\Publico\PortalClienteController;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;

// Raíz pública: con el módulo activo, "/" sirve la landing (no el login).
$router->get('/', [LandingController::class, 'index']);

// Captación de leads (POST público con CSRF).
$router->post('/lead', [LeadController::class, 'capturar'], [CsrfMiddleware::class]);

// Portal cliente genérico (magic-link).
$router->get('/portal', [PortalClienteController::class, 'entrar']);
