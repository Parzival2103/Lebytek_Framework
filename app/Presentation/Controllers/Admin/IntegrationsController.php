<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Integrations\DemoProvisioningService;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Domain\Integrations\IntegrationAccount;
use App\Domain\Integrations\IntegrationAccountRepositoryInterface;
use App\Domain\Integrations\PartnerConnectorInterface;
use App\Infrastructure\Integrations\Http\HttpApiConnector;
use App\Infrastructure\Integrations\Repositories\IntegrationLogRepository;
use App\Kernel\Config\Config;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Kernel\Security\SignedToken;
use App\Presentation\Controllers\AdminBaseController;

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
        $acc = $this->accounts->findDefault('green_api');
        if ($acc === null) {
            return $this->json(['ok' => false, 'error' => 'No hay instancia interna configurada.']);
        }
        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        $url = "{$baseUrl}/waInstance{$acc->instanceId}/getStateInstance/{$acc->token}";
        $res = $http->request('GET', $url);
        $state = (string) (($res['json']['stateInstance'] ?? '') ?: 'desconocido');
        return $this->json(['ok' => true, 'state' => $state]);
    }

    public function provisionForm(Request $request): Response
    {
        return $this->view('admin/integraciones/provision', [
            'titulo'        => 'Provisionar demo WhatsApp',
            'leadId'        => (int) $request->input('lead_id', 0),
            'partnerActivo' => $this->partner->isAvailable(),
        ]);
    }

    public function provision(Request $request): Response
    {
        $this->verifyCsrf($request);
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
            return $this->view('publico/wa_activar', ['error' => 'Enlace inválido o expirado.', 'qr' => null], 'publico/layout');
        }
        $acc = $this->accounts->findById($accountId);
        if ($acc === null) {
            return $this->view('publico/wa_activar', ['error' => 'Instancia no encontrada.', 'qr' => null], 'publico/layout');
        }
        $base = (array) Config::get('integrations.channels.whatsapp.config', []);
        $baseUrl = rtrim((string) ($base['base_url'] ?? 'https://api.green-api.com'), '/');
        $http = new HttpApiConnector((int) ($base['timeout'] ?? 15));
        $res = $http->request('GET', "{$baseUrl}/waInstance{$acc->instanceId}/qr/{$acc->token}");
        $qr = (string) (($res['json']['message'] ?? '') ?: '');
        return $this->view('publico/wa_activar', ['error' => null, 'qr' => $qr], 'publico/layout');
    }
}
