<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Admin;

use Lebytek\Framework\Presentation\Controllers\AdminBaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\UseCases\Dashboard\BuildDashboardViewModelUseCase;
use Lebytek\Framework\Domain\Dashboard\DashboardBuildContext;

final class DashboardController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly BuildDashboardViewModelUseCase $buildDashboardViewModel
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $user = Session::get('auth_user');
        $perms = Session::get('auth_permisos', []);
        $roles = Session::get('auth_roles', []);

        $uid = null;
        if (is_array($user) && isset($user['id'])) {
            $uid = (int) $user['id'];
        }

        if (!is_array($perms)) {
            $perms = [];
        }
        if (!is_array($roles)) {
            $roles = [];
        }

        $perms = array_values(array_map('strval', $perms));
        $roles = array_values(array_map('strval', $roles));

        $dashboard = $this->buildDashboardViewModel->execute(
            new DashboardBuildContext($uid, $perms, $roles)
        );

        return $this->view('admin/dashboard/index', [
            'titulo'    => $dashboard->pageTitle,
            'dashboard' => $dashboard,
        ]);
    }
}
