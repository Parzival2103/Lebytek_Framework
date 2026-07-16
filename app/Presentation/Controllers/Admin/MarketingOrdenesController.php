<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Marketing\ActivarPlanOrdenPagadaUseCase;
use App\Application\Marketing\AutorizarOrdenMembresiaUseCase;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Presentation\Controllers\AdminBaseController;

final class MarketingOrdenesController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly AutorizarOrdenMembresiaUseCase $autorizarOrden,
        private readonly ActivarPlanOrdenPagadaUseCase $activarPlan,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function authorizeForm(Request $request): Response
    {
        $orderId = (int) $request->input('orden_id', 0);
        $order = $orderId > 0 ? $this->orders->findById($orderId) : null;

        return $this->view('admin/marketing/authorize_orden', [
            'titulo' => 'Autorizar pago de membresía',
            'orderId' => $orderId,
            'order' => $order,
            'authorizeEnabled' => $this->authorizeFeatureEnabled(),
        ]);
    }

    public function authorize(Request $request): Response
    {
        $this->verifyCsrf($request);

        if (! $this->authorizeFeatureEnabled()) {
            Session::flash('error', 'La autorización de membresías está deshabilitada (MKT_MEMBERSHIP_AUTHORIZE_ENABLED).');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        $orderId = (int) $request->input('orden_id', 0);
        if ($orderId <= 0) {
            Session::flash('error', 'Orden inválida.');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        $user = Session::get('auth_user');
        $uid = is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
        if ($uid <= 0) {
            Session::flash('error', 'Sesión inválida.');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        try {
            $this->autorizarOrden->ejecutar($orderId, $uid);
            Session::flash('success', 'Membresía autorizada. Se activó el plan en la API y se envió el correo al cliente.');
        } catch (LebytekApiException $e) {
            Session::flash('error', 'Error de API al activar plan: '.$e->getMessage());
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo autorizar: '.$e->getMessage());
        }

        return $this->redirect('/admin/crud/mkt_ordenes');
    }

    public function activatePlanForm(Request $request): Response
    {
        $orderId = (int) $request->input('orden_id', 0);
        $order = $orderId > 0 ? $this->orders->findById($orderId) : null;

        return $this->view('admin/marketing/activate_plan_orden', [
            'titulo' => 'Activar plan en la API',
            'orderId' => $orderId,
            'order' => $order,
            'authorizeEnabled' => $this->authorizeFeatureEnabled(),
        ]);
    }

    public function activatePlan(Request $request): Response
    {
        $this->verifyCsrf($request);

        if (! $this->authorizeFeatureEnabled()) {
            Session::flash('error', 'La activación de membresías está deshabilitada (MKT_MEMBERSHIP_AUTHORIZE_ENABLED).');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        $orderId = (int) $request->input('orden_id', 0);
        if ($orderId <= 0) {
            Session::flash('error', 'Orden inválida.');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        $user = Session::get('auth_user');
        $uid = is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
        if ($uid <= 0) {
            Session::flash('error', 'Sesión inválida.');
            return $this->redirect('/admin/crud/mkt_ordenes');
        }

        try {
            $this->activarPlan->ejecutar($orderId, $uid);
            Session::flash('success', 'Plan activado en la API. Se envió el correo de membresía al cliente.');
        } catch (LebytekApiException $e) {
            Session::flash('error', 'Error de API al activar plan: '.$e->getMessage());
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo activar el plan: '.$e->getMessage());
        }

        return $this->redirect('/admin/crud/mkt_ordenes');
    }

    private function authorizeFeatureEnabled(): bool
    {
        $raw = EnvLoader::get('MKT_MEMBERSHIP_AUTHORIZE_ENABLED', 'false');

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}
