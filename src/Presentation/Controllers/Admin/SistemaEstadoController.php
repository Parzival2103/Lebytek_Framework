<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Admin;

use Lebytek\Framework\Presentation\Controllers\AdminBaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Install\DeploymentStatus;

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
