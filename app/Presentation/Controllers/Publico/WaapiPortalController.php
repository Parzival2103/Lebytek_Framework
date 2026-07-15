<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\WaapiPortalSession;
use App\Infrastructure\Integrations\LebytekApi\ClientTenantApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;

/**
 * Portal cliente waapi — login con token Sanctum y métricas de uso (solo lectura).
 */
final class WaapiPortalController extends BaseController
{
    private const LAYOUT = 'publico/waapi/layout';

    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly WaapiPortalSession $session,
        private readonly ClientTenantApiClient $apiClient,
    ) {}

    public function landing(Request $request): Response
    {
        if ($this->session->isAuthenticated()) {
            return $this->redirect('/portal/dashboard');
        }

        return $this->view('publico/waapi/landing', $this->baseVm([
            'pageTitle' => 'WhatsApp API — Portal cliente',
            'docsUrl' => rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/'),
        ]), self::LAYOUT);
    }

    public function accesoForm(Request $request): Response
    {
        if ($this->session->isAuthenticated()) {
            return $this->redirect('/portal/dashboard');
        }

        return $this->view('publico/waapi/acceso', $this->baseVm([
            'pageTitle' => 'Iniciar sesión',
            'error' => null,
        ]), self::LAYOUT);
    }

    public function accesoSubmit(Request $request): Response
    {
        $token = trim((string) $request->input('token', ''));

        if (! $this->session->login($token)) {
            return $this->view('publico/waapi/acceso', $this->baseVm([
                'pageTitle' => 'Iniciar sesión',
                'error' => 'Token inválido o expirado. Pega el valor completo del correo (incluye el número y |).',
            ]), self::LAYOUT);
        }

        return $this->redirect('/portal/dashboard');
    }

    public function dashboard(Request $request): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $token = (string) $this->session->token();
        $usage = null;
        $usageError = null;

        try {
            $usage = $this->apiClient->getUsage($token);
        } catch (LebytekApiException $e) {
            $usageError = $e->getMessage();
        }

        return $this->view('publico/waapi/dashboard', $this->baseVm([
            'pageTitle' => 'Uso de mensajes',
            'showLogout' => true,
            'usage' => is_array($usage) ? $usage : null,
            'usageError' => $usageError,
            'docsUrl' => rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/'),
        ]), self::LAYOUT);
    }

    public function logout(Request $request): Response
    {
        $this->session->logout();

        return $this->redirect('/portal/acceso');
    }

    /** @param array<string, mixed> $extra */
    private function baseVm(array $extra = []): array
    {
        return array_merge([
            'empresaNombre' => $this->configuracionService->empresaNombre(),
            'empresaLogo' => $this->configuracionService->empresaLogo(),
        ], $extra);
    }

    private function requireAuth(): ?Response
    {
        if ($this->session->isAuthenticated()) {
            return null;
        }

        return $this->redirect('/portal/acceso');
    }
}
