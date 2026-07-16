<?php

declare(strict_types=1);

use App\Presentation\Controllers\Admin\MarketingExperimentsController;
use App\Presentation\Controllers\Admin\MarketingLeadsController;
use App\Presentation\Controllers\Admin\MarketingOrdenesController;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;
use Lebytek\Framework\Presentation\Middlewares\RbacMiddleware;

/** @var \Lebytek\Framework\Kernel\Http\Router $router */

$rbacLeads = [new RbacMiddleware('marketing.leads')];
$rbacOrdenes = [new RbacMiddleware('marketing.ordenes')];
$rbacExperimentos = [new RbacMiddleware('marketing.experimentos')];

$router->get('/marketing/leads/provision-api', [MarketingLeadsController::class, 'provisionForm'], $rbacLeads);
$router->post('/marketing/leads/provision-api', [MarketingLeadsController::class, 'provisionViaApi'], array_merge($rbacLeads, [CsrfMiddleware::class]));
$router->get('/marketing/leads/deprovision-api', [MarketingLeadsController::class, 'deprovisionForm'], $rbacLeads);
$router->post('/marketing/leads/deprovision-api', [MarketingLeadsController::class, 'deprovisionViaApi'], array_merge($rbacLeads, [CsrfMiddleware::class]));

$router->get('/marketing/ordenes/autorizar', [MarketingOrdenesController::class, 'authorizeForm'], $rbacOrdenes);
$router->post('/marketing/ordenes/autorizar', [MarketingOrdenesController::class, 'authorize'], array_merge($rbacOrdenes, [CsrfMiddleware::class]));

$router->get('/marketing/experimentos', [MarketingExperimentsController::class, 'index'], $rbacExperimentos);
$router->post('/marketing/experimentos/accept', [MarketingExperimentsController::class, 'accept'], array_merge($rbacExperimentos, [CsrfMiddleware::class]));
$router->post('/marketing/experimentos/reject', [MarketingExperimentsController::class, 'reject'], array_merge($rbacExperimentos, [CsrfMiddleware::class]));
