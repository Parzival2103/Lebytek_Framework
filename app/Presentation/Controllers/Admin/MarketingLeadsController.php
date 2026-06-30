<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Marketing\LeadApiProvisioningService;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;

final class MarketingLeadsController extends BaseController
{
    public function __construct(
        private readonly LeadApiProvisioningService $provisioning,
    ) {}

    public function provisionForm(Request $request): Response
    {
        return $this->view('admin/marketing/provision_api', [
            'titulo' => 'Provisionar demo vía api.lebytek.com',
            'leadId' => (int) $request->input('lead_id', 0),
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
            $this->provisioning->provisionLead($leadId);
            Session::flash('success', 'Demo provisionada vía api.lebytek.com. Se envió el correo con credenciales al cliente.');
        } catch (LebytekApiException $e) {
            Session::flash('error', 'Error de api: '.$e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo provisionar: '.$e->getMessage());
        }

        return $this->redirect('/admin/crud/mkt_leads');
    }
}
