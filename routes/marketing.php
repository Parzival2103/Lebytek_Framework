<?php

// routes/marketing.php
// Rutas del módulo Marketing. Incluido condicionalmente desde routes/web.php
// solo cuando vertical.modules.marketing === true. Tiene $router en scope.

use App\Presentation\Controllers\Publico\LandingController;
use App\Presentation\Middlewares\CsrfMiddleware;

// Raíz pública: con el módulo activo, "/" sirve la landing (no el login).
$router->get('/', [LandingController::class, 'index']);

// (Task 13 añade aquí el POST público de captación con CsrfMiddleware.)
// (Task 14 añade aquí el portal cliente magic-link.)
