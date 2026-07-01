<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Application\Services\ConfiguracionService;
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
        $ui = LebytekUiConfig::resolve($this->configuracionService->all());

        $nombre = $this->configuracionService->empresaNombre();

        return $this->view('publico/landing', [
            'pageTitle'           => $nombre . ' — WhatsApp Business',
            'metaDescription'     => 'Automatiza WhatsApp para tu negocio: campañas masivas, demo en minutos y panel multi-usuario con Lebytek.',
            'empresaNombre'       => $nombre,
            'empresaLogo'         => $this->configuracionService->empresaLogo(),
            'bloques'             => $vm['bloques'],
            'paquetes'            => $vm['paquetes'],
            'primaryColor'        => $ui['primaryColor'],
            'primaryHover'        => $ui['primaryHover'],
            'primaryActive'       => $ui['primaryActive'],
            'primarySubtle'       => $ui['primarySubtle'],
            'primaryRgb'          => $ui['primaryRgb'],
            'lebytekCssVariables' => $ui['lebytekCssVariables'],
            'bodyBg'              => $ui['bodyBg'],
            'darkMode'            => $ui['darkMode'],
        ], 'publico/layout');
    }
}
