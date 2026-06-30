<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Admin;

use Lebytek\Framework\Application\Integrations\DemoProvisioningService;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Domain\Integrations\IntegrationAccount;
use Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface;
use Lebytek\Framework\Domain\Integrations\PartnerConnectorInterface;
use Lebytek\Framework\Infrastructure\Integrations\GreenApi\GreenApiAccountClient;
use Lebytek\Framework\Infrastructure\Integrations\Http\HttpApiConnector;
use Lebytek\Framework\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Security\SignedToken;
use Lebytek\Framework\Presentation\Controllers\AdminBaseController;

final class IntegrationsController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly IntegrationAccountRepositoryInterface $accounts,
        private readonly PartnerConnectorInterface $partner,
        private readonly DemoProvisioningService $provisioning,
        private readonly IntegrationLogRepository $logs,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/integraciones/index', [
            'titulo'        => 'Integraciones / WhatsApp',
            'instancia'     => $this->accounts->findDefault('green_api'),
            'partnerActivo' => $this->partner->isAvailable(),
            'logs'          => $this->logs->recent(50),
        ]);
    }

    public function saveInternal(Request $request): Response
    {
        $this->verifyCsrf($request);
        $instanceId = trim((string) $request->input('instance_id', ''));
        $token = trim((string) $request->input('token', ''));
        if ($instanceId === '' || $token === '') {
            Session::flash('error', 'Instance ID y token son obligatorios.');
            return $this->redirect('/admin/integraciones');
        }
        $existing = $this->accounts->findDefault('green_api');
        $id = $this->accounts->save(new IntegrationAccount(
            $existing?->id ?? 0,
            'green_api',
            'Instancia interna',
            $instanceId,
            $token,
            true,
            null,
            'manual',
            'manual'
        ));
        $this->accounts->markDefault($id, 'green_api');
        Session::flash('success', 'Instancia interna guardada.');
        return $this->redirect('/admin/integraciones');
    }

    public function testConnection(Request $request): Response
    {
        $this->verifyCsrf($request);

        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $resolved = \Lebytek\Framework\Application\Integrations\IntegrationsFactory::resolveWhatsappConfig($base);
        $instanceId = trim((string) ($resolved['instance_id'] ?? ''));
        $token = trim((string) ($resolved['token'] ?? ''));

        if ($instanceId === '' || $token === '') {
            return $this->json([
                'ok'    => false,
                'error' => 'No hay instancia configurada. Guarda instance ID + token arriba, o define GREEN_API_INSTANCE/GREEN_API_TOKEN en .env.',
            ]);
        }

        $baseUrl = rtrim((string) ($resolved['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($resolved['timeout'] ?? 15));
        $url = "{$baseUrl}/waInstance{$instanceId}/getStateInstance/{$token}";
        $res = $http->request('GET', $url);

        if ((int) ($res['status'] ?? 0) === 0) {
            return $this->json([
                'ok'    => false,
                'error' => 'No se pudo contactar Green API: ' . (string) ($res['body'] ?? 'error de transporte'),
            ]);
        }

        $state = (string) (($res['json']['stateInstance'] ?? '') ?: 'desconocido');
        return $this->json(['ok' => true, 'state' => $state]);
    }

    public function provisionForm(Request $request): Response
    {
        if ($this->apiProvisioningEnabled()) {
            Session::flash('error', 'Provisión local desactivada: usa "Provisionar demo (api)" en Leads (LEBYTEK_API_TOKEN configurado).');
            return $this->redirect('/admin/crud/mkt_leads');
        }

        return $this->view('admin/integraciones/provision', [
            'titulo'        => 'Provisionar demo WhatsApp',
            'leadId'        => (int) $request->input('lead_id', 0),
            'partnerActivo' => $this->partner->isAvailable(),
        ]);
    }

    public function provision(Request $request): Response
    {
        $this->verifyCsrf($request);

        if ($this->apiProvisioningEnabled()) {
            Session::flash('error', 'Provisión local desactivada: usa "Provisionar demo (api)" en Leads.');
            return $this->redirect('/admin/crud/mkt_leads');
        }

        $leadId = (int) $request->input('lead_id', 0);
        $nombre = trim((string) $request->input('lead_nombre', ''));
        $email  = trim((string) $request->input('lead_email', ''));
        if ($leadId <= 0 || $email === '') {
            Session::flash('error', 'Lead inválido o sin correo.');
            return $this->redirect('/admin/integraciones');
        }
        try {
            if ($this->partner->isAvailable() && $request->input('modo', 'manual') === 'auto') {
                $this->provisioning->provisionAuto($leadId, $nombre, $email);
            } else {
                $instanceId = trim((string) $request->input('instance_id', ''));
                $token = trim((string) $request->input('token', ''));
                if ($instanceId === '' || $token === '') {
                    Session::flash('error', 'Sin Partner API: ingresa instance_id y token.');
                    return $this->redirect('/admin/integraciones/provision?lead_id=' . $leadId);
                }
                $this->provisioning->provisionManual($leadId, $nombre, $email, $instanceId, $token);
            }
            Session::flash('success', 'Demo provisionada y correo enviado.');
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo provisionar: ' . $e->getMessage());
        }
        return $this->redirect('/admin/integraciones');
    }

    public function activar(Request $request): Response
    {
        $token = (string) $request->param('token', '');
        $accountId = SignedToken::verify($token);
        if ($accountId === null) {
            return $this->view('publico/wa_activar', [
                'phase' => 'error',
                'message' => 'Enlace inválido o expirado.',
                'qr' => null,
                'qr_url' => null,
                'state' => null,
                'api_docs_url' => (string) Config::get('integrations.activation.api_docs_url', '/docs/integraciones/whatsapp-api'),
                'status_url' => null,
            ], 'publico/layout');
        }
        $acc = $this->accounts->findById($accountId);
        if ($acc === null) {
            return $this->view('publico/wa_activar', [
                'phase' => 'error',
                'message' => 'Instancia no encontrada.',
                'qr' => null,
                'qr_url' => null,
                'state' => null,
                'api_docs_url' => (string) Config::get('integrations.activation.api_docs_url', '/docs/integraciones/whatsapp-api'),
                'status_url' => null,
            ], 'publico/layout');
        }
        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        $client = new GreenApiAccountClient($http, $baseUrl);
        $phase = $client->resolveActivationPhase($acc->instanceId, $acc->token);
        $apiDocsUrl = (string) Config::get('integrations.activation.api_docs_url', '/docs/integraciones/whatsapp-api');

        return $this->view('publico/wa_activar', [
            'phase'        => $phase['phase'],
            'message'      => $phase['message'],
            'qr'           => $phase['qr_base64'],
            'qr_url'       => $phase['qr_url'],
            'state'        => $phase['state'],
            'api_docs_url' => $apiDocsUrl,
            'status_url'   => '/wa/activar/' . rawurlencode($token) . '/estado',
        ], 'publico/layout');
    }

    public function activarEstado(Request $request): Response
    {
        $token = (string) $request->param('token', '');
        $accountId = SignedToken::verify($token);
        if ($accountId === null) {
            return $this->json(['ok' => false, 'phase' => 'error', 'message' => 'Enlace inválido o expirado.']);
        }

        $acc = $this->accounts->findById($accountId);
        if ($acc === null) {
            return $this->json(['ok' => false, 'phase' => 'error', 'message' => 'Instancia no encontrada.']);
        }

        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        $phase = (new GreenApiAccountClient($http, $baseUrl))->resolveActivationPhase($acc->instanceId, $acc->token);

        return $this->json([
            'ok'         => $phase['phase'] !== 'error',
            'phase'      => $phase['phase'],
            'message'    => $phase['message'],
            'qr_base64'  => $phase['qr_base64'],
            'qr_url'     => $phase['qr_url'],
            'state'      => $phase['state'],
            'docs_url'   => (string) Config::get('integrations.activation.api_docs_url', '/docs/integraciones/whatsapp-api'),
        ]);
    }

    private function apiProvisioningEnabled(): bool
    {
        return trim((string) \Lebytek\Framework\Kernel\EnvLoader::get('LEBYTEK_API_TOKEN', '')) !== '';
    }
}
