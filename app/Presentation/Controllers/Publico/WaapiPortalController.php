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

final class WaapiPortalController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly WaapiPortalSession $session,
        private readonly ClientTenantApiClient $apiClient,
    ) {}

    public function landing(Request $request): Response
    {
        return $this->view('publico/waapi/landing', $this->baseVm([
            'pageTitle' => 'WhatsApp API — Lebytek',
            'docsUrl' => rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/'),
        ]), 'publico/layout');
    }

    public function accesoForm(Request $request): Response
    {
        if ($this->session->isAuthenticated()) {
            return $this->redirect('/portal/dashboard');
        }

        return $this->view('publico/waapi/acceso', $this->baseVm([
            'pageTitle' => 'Acceder al panel',
            'error' => null,
        ]), 'publico/layout');
    }

    public function accesoSubmit(Request $request): Response
    {
        $token = trim((string) $request->input('token', ''));

        if (! $this->session->login($token)) {
            return $this->view('publico/waapi/acceso', $this->baseVm([
                'pageTitle' => 'Acceder al panel',
                'error' => 'Token inválido o expirado. Revisa el correo de credenciales e inténtalo de nuevo.',
            ]), 'publico/layout');
        }

        return $this->redirect('/portal/dashboard');
    }

    public function dashboard(Request $request): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $token = (string) $this->session->token();
        $instances = $this->apiClient->listInstances($token);
        $instance = $instances[0] ?? null;

        return $this->view('publico/waapi/dashboard', $this->baseVm([
            'pageTitle' => 'Panel cliente',
            'instance' => $instance,
            'docsUrl' => rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/'),
        ]), 'publico/layout');
    }

    public function qr(Request $request): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $token = (string) $this->session->token();
        $instances = $this->apiClient->listInstances($token);
        $instance = $instances[0] ?? null;
        $instancePublicId = is_array($instance) ? (string) ($instance['publicId'] ?? '') : '';

        $qr = null;
        $error = null;
        $phase = 'error';

        if ($instancePublicId === '') {
            $error = 'No hay instancia asociada a tu cuenta.';
        } elseif (($instance['status'] ?? '') === 'authorized') {
            $phase = 'ready';
        } else {
            try {
                $qrResponse = $this->apiClient->getQr($token, $instancePublicId);
                $qr = (string) ($qrResponse['qr'] ?? '');
                $phase = $qr !== '' ? 'awaiting_scan' : 'error';
                if ($qr === '') {
                    $error = 'No se pudo obtener el código QR.';
                }
            } catch (LebytekApiException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->view('publico/waapi/qr', $this->baseVm([
            'pageTitle' => 'Conectar WhatsApp',
            'phase' => $phase,
            'qr' => $qr,
            'error' => $error,
            'instance' => $instance,
            'statusUrl' => '/portal/qr/estado?instancePublicId='.urlencode($instancePublicId),
            'docsUrl' => rtrim((string) EnvLoader::get('MKT_EMAIL_DOCS_URL', 'https://docs.lebytek.com'), '/'),
        ]), 'publico/layout');
    }

    public function qrEstado(Request $request): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $token = (string) $this->session->token();
        $publicId = (string) $request->input('instancePublicId', '');

        if ($publicId === '') {
            return Response::json(['phase' => 'error', 'message' => 'Instancia no especificada'], 400);
        }

        try {
            $instance = $this->apiClient->getInstance($token, $publicId);
            $status = (string) ($instance['status'] ?? '');

            if ($status === 'authorized') {
                return Response::json([
                    'phase' => 'ready',
                    'message' => 'Tu instancia ya está autorizada y lista para usar la API.',
                ]);
            }

            if (in_array($status, ['waiting_qr', 'configuring'], true)) {
                return Response::json([
                    'phase' => 'awaiting_scan',
                    'message' => 'Esperando escaneo del código QR.',
                ]);
            }

            return Response::json([
                'phase' => 'syncing',
                'message' => 'Sincronizando instancia con WhatsApp…',
            ]);
        } catch (LebytekApiException $e) {
            return Response::json(['phase' => 'error', 'message' => $e->getMessage()], 502);
        }
    }

    public function uso(Request $request): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        return $this->view('publico/waapi/uso', $this->baseVm([
            'pageTitle' => 'Resumen de uso',
            'usageAvailable' => false,
            'messagesSent' => null,
            'messagesReceived' => null,
        ]), 'publico/layout');
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
