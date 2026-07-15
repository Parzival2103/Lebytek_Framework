<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use App\Application\Marketing\RenderLandingUseCase;
use App\Application\Marketing\LandingExperimentAssigner;
use App\Application\Marketing\MergeLandingVariantUseCase;
use App\Application\Marketing\AssignInput;
use App\Presentation\Marketing\LandingSectionRenderer;
use Lebytek\Framework\Kernel\EnvLoader;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly RenderLandingUseCase $renderLanding,
        private readonly LandingExperimentAssigner $assigner,
        private readonly MergeLandingVariantUseCase $merge,
        private readonly LandingSectionRenderer $sectionRenderer
    ) {}

    public function index(Request $request): Response
    {
        // Anti-deuda §X: Request::isSecure() no existe — resolver HTTPS aquí
        // (Presentation) y reusar el mismo flag al aplicar setcookie().
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || str_starts_with((string) EnvLoader::get('APP_URL', ''), 'https');

        $force = strtolower(trim((string) $request->query('variant', '')));
        if ($force === '') {
            $force = strtolower(trim((string) $request->query('landing', '')));
        }

        $assigned = $this->assigner->assign(new AssignInput(
            forceVariant: $force,
            cookies: [
                'lb_vid' => (string) $request->cookie('lb_vid', ''),
                'lb_var' => (string) $request->cookie('lb_var', ''),
                'lb_preview' => (string) $request->cookie('lb_preview', ''),
            ],
            isHttps: $secure,
        ));

        $this->applyCookies($assigned->cookies, $secure);

        $vm = $this->renderLanding->ejecutar('home');
        $merged = $this->merge->merge($assigned->slug, $vm['bloques']);

        $ui = LebytekUiConfig::resolve($this->configuracionService->all());
        $nombre = $this->configuracionService->empresaNombre();
        $comprasHabilitadas = filter_var($request->query('compras', false), FILTER_VALIDATE_BOOLEAN);

        $useV2 = $merged['shell'] === 'v2';
        $view = $useV2 ? 'publico/landing_v2' : 'publico/landing';
        $layout = $useV2 ? 'publico/layout_v2' : 'publico/layout';

        $sectionsHtml = $this->sectionRenderer->render($merged['shell'], $merged['sections'], [
            'bloques' => $merged['bloques'],
            'paquetes' => $vm['paquetes'],
            'comprasHabilitadas' => $comprasHabilitadas,
            'landingVariant' => $assigned->slug,
            'visitorId' => $assigned->visitorId,
        ]);

        return $this->view($view, [
            'pageTitle'           => $merged['seo']['title'],
            'metaDescription'     => $merged['seo']['description'],
            'empresaNombre'       => $nombre,
            'empresaLogo'         => $this->configuracionService->empresaLogo(),
            'bloques'             => $merged['bloques'],
            'paquetes'            => $vm['paquetes'],
            'comprasHabilitadas'  => $comprasHabilitadas,
            'sections'            => $merged['sections'],
            'shell'               => $merged['shell'],
            'sectionsHtml'        => $sectionsHtml,
            'landingVariant'      => $assigned->slug,
            'visitorId'           => $assigned->visitorId,
            'isPreview'           => $assigned->isPreview,
            'primaryColor'        => $ui['primaryColor'],
            'primaryHover'        => $ui['primaryHover'],
            'primaryActive'       => $ui['primaryActive'],
            'primarySubtle'       => $ui['primarySubtle'],
            'primaryRgb'          => $ui['primaryRgb'],
            'lebytekCssVariables' => $ui['lebytekCssVariables'],
            'bodyBg'              => $ui['bodyBg'],
            'darkMode'            => $ui['darkMode'],
        ], $layout);
    }

    /** @param list<\App\Application\Marketing\CookieSpec> $cookies */
    private function applyCookies(array $cookies, bool $secure): void
    {
        foreach ($cookies as $cookie) {
            $opts = [
                'path' => $cookie->path,
                'secure' => $secure,
                'httponly' => $cookie->httpOnly,
                'samesite' => $cookie->sameSite,
            ];

            if ($cookie->delete) {
                $opts['expires'] = time() - 3600;
                setcookie($cookie->name, '', $opts);
                continue;
            }

            $opts['expires'] = $cookie->maxAgeSeconds !== null
                ? time() + $cookie->maxAgeSeconds
                : time() + (int) $cookie->ttlDays * 86400;
            setcookie($cookie->name, $cookie->value, $opts);
        }
    }
}
