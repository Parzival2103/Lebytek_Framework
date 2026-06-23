<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Presentation\Controllers\AdminBaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\Session;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\SettingsSectionRegistry;

final class AjustesController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly SettingsSectionRegistry $settingsSections
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        $permisos = Session::get('auth_permisos', []);
        return $this->view('admin/ajustes/index', [
            'titulo'           => 'Ajustes del sistema',
            'configuracion'    => $this->configuracionService->all(),
            'settingsSections' => $this->settingsSections->visibles($permisos),
        ]);
    }

    public function guardar(Request $request): Response
    {
        $this->verifyCsrf($request);

        $campos = [
            'empresa_nombre',
            'menu_layout',
            'primary_color',
            'navbar_color',
            'body_color',
            'empresa_logo',
        ];

        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = $request->input($campo, '');
        }

        $datos['dark_mode'] = $request->has('dark_mode') ? '1' : '0';
        $datos['empresa_mostrar_nombre'] = $request->has('empresa_mostrar_nombre') ? '1' : '0';

        $datos = array_merge($datos, $this->validatedLebytekUiSettings($request));

        // Campos declarados por providers de secciones (módulos activos), filtrados por RBAC.
        $permisos = Session::get('auth_permisos', []);
        foreach ($this->settingsSections->fieldNames($permisos) as $campo) {
            // Los toggles llegan ausentes cuando están desmarcados.
            $datos[$campo] = $request->has($campo) ? (string) $request->input($campo, '1') : '0';
            // Para campos de texto, conservar el valor textual si vino con contenido.
            $valor = $request->input($campo, null);
            if ($valor !== null && $valor !== '' && $valor !== '1') {
                $datos[$campo] = (string) $valor;
            }
        }

        $this->configuracionService->setMultiple($datos);
        AdminBaseController::resetSystemConfigCache();

        Session::flash('success', 'Ajustes guardados correctamente.');
        return $this->redirect('/admin/ajustes');
    }

    /**
     * @return array<string, string>
     */
    private function validatedLebytekUiSettings(Request $request): array
    {
        $layout = strtolower((string) $request->input('ui_layout_width', 'fluid'));
        if (!in_array($layout, ['fluid', 'boxed'], true)) {
            $layout = 'fluid';
        }

        $density = strtolower((string) $request->input('ui_content_density', 'comfortable'));
        if (!in_array($density, ['comfortable', 'compact'], true)) {
            $density = 'comfortable';
        }

        $cardStyle = strtolower((string) $request->input('ui_card_style', 'soft'));
        if (!in_array($cardStyle, ['soft', 'bordered', 'flat'], true)) {
            $cardStyle = 'soft';
        }

        $tableDensity = strtolower((string) $request->input('ui_table_density', 'normal'));
        if (!in_array($tableDensity, ['normal', 'compact'], true)) {
            $tableDensity = 'normal';
        }

        $radius = strtolower(trim((string) $request->input('theme_border_radius', 'md')));
        if (!in_array($radius, ['sm', 'md', 'lg', 'xl', 'xs'], true)) {
            $radius = 'md';
        }

        $shadowRaw = $request->input('theme_shadow_level', '1');
        $shadow = is_numeric($shadowRaw) ? (int) $shadowRaw : 1;
        $shadow = (string) max(0, min(3, $shadow));

        $animations = $request->has('ui_enable_animations') ? '1' : '0';

        return [
            'ui_layout_width'      => $layout,
            'ui_content_density'   => $density,
            'ui_card_style'        => $cardStyle,
            'ui_table_density'     => $tableDensity,
            'ui_enable_animations' => $animations,
            'theme_border_radius'  => $radius,
            'theme_shadow_level'   => $shadow,
        ];
    }

    public function toggleTema(Request $request): Response
    {
        if (!$request->isAjax() && !$request->isPost()) {
            return $this->json(['error' => 'Método no permitido.'], 405);
        }

        $nuevo = $this->configuracionService->toggleDarkMode();
        AdminBaseController::resetSystemConfigCache();

        return $this->json(['dark_mode' => $nuevo]);
    }
}
