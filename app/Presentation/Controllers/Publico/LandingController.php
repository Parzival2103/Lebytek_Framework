<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Application\Services\ConfiguracionService;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService
    ) {}

    public function index(Request $request): Response
    {
        return $this->view('publico/landing', [
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo'   => $this->configuracionService->empresaLogo(),
            'bloques'       => [],   // Task 12 lo reemplaza por el provider de contenido
            'paquetes'      => [],   // Task 12 lo reemplaza por el package source
        ], 'publico/layout');
    }
}
