<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Install\DeploymentStatus;

final class SistemaEstadoController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly DeploymentStatus $deploymentStatus
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/sistema/estado', [
            'titulo' => 'Estado del sistema',
            'estado' => $this->deploymentStatus->reporte(),
        ]);
    }
}
