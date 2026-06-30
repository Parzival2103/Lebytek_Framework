<?php

declare(strict_types=1);

use App\Presentation\Controllers\Admin\MarketingLeadsController;
use Lebytek\Framework\Presentation\Middlewares\CsrfMiddleware;
use Lebytek\Framework\Presentation\Middlewares\RbacMiddleware;

/** @var \Lebytek\Framework\Kernel\Http\Router $router */

$rbacLeads = [new RbacMiddleware('marketing.leads')];

$router->get('/marketing/leads/provision-api', [MarketingLeadsController::class, 'provisionForm'], $rbacLeads);
$router->post('/marketing/leads/provision-api', [MarketingLeadsController::class, 'provisionViaApi'], array_merge($rbacLeads, [CsrfMiddleware::class]));
