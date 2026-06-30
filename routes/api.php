<?php

use Lebytek\Framework\Presentation\Middlewares\AuthMiddleware;
use Lebytek\Framework\Presentation\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| Rutas API JSON
|--------------------------------------------------------------------------
| Misma arquitectura que las rutas web.
| Responden con JSON. Autenticación futura mediante token.
*/

$router->group([
    'prefix'      => '/api',
    'middlewares' => [AuthMiddleware::class],
], function ($router) {

    // Placeholder — descomentar al implementar controladores API
    // $router->get('/usuarios',     [Lebytek\Framework\Presentation\Api\UsuariosApiController::class, 'index']);
    // $router->get('/usuarios/{id}',[Lebytek\Framework\Presentation\Api\UsuariosApiController::class, 'show']);

    $router->get('/ping', [HealthController::class, 'ping']);
});
