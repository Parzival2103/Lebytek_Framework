<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers;

use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Helpers\LebytekUiConfig;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Domain\Policies\RbacPolicy;

abstract class AdminBaseController extends BaseController
{
    private static ?array $systemConfigCache = null;

    public function __construct(
        protected readonly ConfiguracionService $configuracionService,
        protected readonly AdminNavigationMenuService $adminNavigationMenuService
    ) {}

    protected function view(string $view, array $data = [], string $layout = 'layouts/base'): Response
    {
        if ($layout !== '') {
            $data = array_merge($this->cargarDatosSistema(), $data);
        }

        return parent::view($view, $data, $layout);
    }

    protected function cargarConfiguracionSistema(): array
    {
        if (self::$systemConfigCache !== null) {
            return self::$systemConfigCache;
        }

        try {
            $sysConfig = $this->configuracionService->all();
            self::$systemConfigCache = LebytekUiConfig::resolve(is_array($sysConfig) ? $sysConfig : []);
        } catch (\Throwable) {
            self::$systemConfigCache = LebytekUiConfig::resolve([]);
        }

        return self::$systemConfigCache;
    }

    /**
     * Invalida la caché de tema tras guardar cfg_configuraciones (evita datos obsoletos en el mismo worker FPM).
     */
    public static function resetSystemConfigCache(): void
    {
        self::$systemConfigCache = null;
    }

    protected function filtrarMenuPorPermisos(): array
    {
        $permisos = Session::get('auth_permisos', []);
        $roles    = Session::get('auth_roles', []);

        return $this->adminNavigationMenuService->menuFiltradoParaUsuario($permisos, $roles);
    }

    private function cargarDatosSistema(): array
    {
        $config = $this->cargarConfiguracionSistema();
        $uri    = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $rbac   = new RbacPolicy(
            Session::get('auth_permisos', []),
            Session::get('auth_roles', [])
        );

        return array_merge($config, [
            'usuario'                  => Session::get('auth_user', []),
            'flashAll'                 => Session::flashAll(),
            'menuFiltrado'             => $this->filtrarMenuPorPermisos(),
            'currentUri'               => $uri,
            'puedeGestionarApariencia' => $rbac->puede('administracion.ver'),
        ]);
    }
}
