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
            'pageTitle'           => 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas',
            'metaDescription'     => 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Planes desde $2,199/mes. Demo inmediata.',
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
