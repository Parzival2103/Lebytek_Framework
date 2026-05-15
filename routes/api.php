<?php

use App\Presentation\Middlewares\AuthMiddleware;
use App\Presentation\Controllers\Api\HealthController;

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
    // $router->get('/usuarios',     [App\Presentation\Api\UsuariosApiController::class, 'index']);
    // $router->get('/usuarios/{id}',[App\Presentation\Api\UsuariosApiController::class, 'show']);

    $router->get('/ping', [HealthController::class, 'ping']);
});
