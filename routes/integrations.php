<?php

declare(strict_types=1);

use App\Presentation\Controllers\Admin\IntegrationsController;
use App\Presentation\Middlewares\CsrfMiddleware;
use App\Presentation\Middlewares\RbacMiddleware;

/** @var \App\Kernel\Http\Router $router */

$rbacVer = [new RbacMiddleware('integrations.ver')];
$rbacConfig = [new RbacMiddleware('integrations.configurar')];
$rbacEnviar = [new RbacMiddleware('integrations.enviar')];

$router->get('/integraciones', [IntegrationsController::class, 'index'], $rbacVer);
$router->post('/integraciones/config/internal', [IntegrationsController::class, 'saveInternal'], array_merge($rbacConfig, [CsrfMiddleware::class]));
$router->post('/integraciones/test', [IntegrationsController::class, 'testConnection'], array_merge($rbacConfig, [CsrfMiddleware::class]));
$router->get('/integraciones/provision', [IntegrationsController::class, 'provisionForm'], $rbacEnviar);
$router->post('/integraciones/provision', [IntegrationsController::class, 'provision'], array_merge($rbacEnviar, [CsrfMiddleware::class]));
