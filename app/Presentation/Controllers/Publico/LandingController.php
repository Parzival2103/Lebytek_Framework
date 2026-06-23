<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;
use App\Application\Marketing\RenderLandingUseCase;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly RenderLandingUseCase $renderLanding
    ) {}

    public function index(Request $request): Response
    {
        $vm = $this->renderLanding->ejecutar('home');

        return $this->view('publico/landing', [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'bloques'       => $vm['bloques'],
            'paquetes'      => $vm['paquetes'],
        ], 'publico/layout');
    }
}
