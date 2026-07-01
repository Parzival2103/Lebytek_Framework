<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Marketing\LeadApiDeprovisioningService;
use App\Application\Marketing\LeadApiProvisioningService;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Presentation\Controllers\AdminBaseController;

final class MarketingLeadsController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly LeadApiProvisioningService $provisioning,
        private readonly LeadApiDeprovisioningService $deprovisioning,
        private readonly LeadRepositoryInterface $leads,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function provisionForm(Request $request): Response
    {
        $leadId = (int) $request->input('lead_id', 0);
        $lead = $leadId > 0 ? $this->leads->findById($leadId) : null;

        return $this->view('admin/marketing/provision_api', [
            'titulo' => 'Provisionar demo vía api.lebytek.com',
            'leadId' => $leadId,
            'lead'   => $lead,
        ]);
    }

    public function provisionViaApi(Request $request): Response
    {
        $this->verifyCsrf($request);

        $leadId = (int) $request->input('lead_id', 0);
        if ($leadId <= 0) {
            Session::flash('error', 'Lead inválido.');
            return $this->redirect('/admin/crud/mkt_leads');
        }

        try {
            $result = $this->provisioning->provisionLead($leadId);
            match ($result['status']) {
                'skipped' => Session::flash('info', 'Este lead ya tiene demo activa en la API.'),
                'mail_failed' => Session::flash(
                    'error',
                    'No se marcó la demo como enviada: falló el correo con credenciales ('.($result['message'] ?? 'error desconocido').').',
                ),
                default => Session::flash('success', 'Demo provisionada vía api.lebytek.com. Correo con credenciales enviado al cliente. Green API sigue provisionando la instancia en segundo plano.'),
            };
        } catch (LebytekApiException $e) {
            Session::flash('error', 'Error de api: '.$e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo provisionar: '.$e->getMessage());
        }

        return $this->redirect('/admin/crud/mkt_leads');
    }

    public function deprovisionForm(Request $request): Response
    {
        $leadId = (int) $request->input('lead_id', 0);
        $lead = $leadId > 0 ? $this->leads->findById($leadId) : null;

        return $this->view('admin/marketing/deprovision_api', [
            'titulo' => 'Dar de baja demo en api.lebytek.com',
            'leadId' => $leadId,
            'lead'   => $lead,
        ]);
    }

    public function deprovisionViaApi(Request $request): Response
    {
        $this->verifyCsrf($request);

        $leadId = (int) $request->input('lead_id', 0);
        if ($leadId <= 0) {
            Session::flash('error', 'Lead inválido.');
            return $this->redirect('/admin/crud/mkt_leads');
        }

        try {
            $result = $this->deprovisioning->deprovisionLead($leadId);
            $count = (int) ($result['deleted'] ?? 0);
            Session::flash(
                'info',
                $count === 1
                    ? 'Baja iniciada: la instancia WhatsApp se está eliminando en Green API. El lead pasará a «demo dada de baja» cuando se confirme.'
                    : "Baja iniciada: se encolaron {$count} instancias para eliminación. El lead pasará a «demo dada de baja» cuando se confirme.",
            );
        } catch (LebytekApiException $e) {
            Session::flash('error', 'Error de api al dar de baja: '.$e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo dar de baja: '.$e->getMessage());
        }

        return $this->redirect('/admin/crud/mkt_leads');
    }
}
