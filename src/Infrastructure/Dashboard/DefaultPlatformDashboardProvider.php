<?php

declare(strict_types=1);

namespace App\Infrastructure\Dashboard;

use App\Domain\Dashboard\DashboardBuildContext;
use App\Domain\Dashboard\DashboardContribution;
use App\Domain\Interfaces\DashboardContributionProviderInterface;

/**
 * Contenido dashboard por defecto (plataforma: administración + ajustes + mensaje instalación limpia).
 */
final class DefaultPlatformDashboardProvider implements DashboardContributionProviderInterface
{
    public function priority(): int
    {
        return 10;
    }

    public function contribute(DashboardBuildContext $context): DashboardContribution
    {
        $activity = [];
        if ($context->usuarioId !== null) {
            $activity[] = [
                'icon' => 'bi-person-check',
                'text' => 'Sesión activa en el panel administrativo.',
                'meta' => 'Framework base',
            ];
        }

        $kpis = [];
        if ($context->tienePermiso('usuarios.gestionar')) {
            $kpis[] = [
                'label'       => 'Usuarios',
                'value'       => '—',
                'icon'        => 'bi-people-fill',
                'color'       => 'primary',
                'url'         => '/admin/administracion/usuarios',
                'description' => 'Gestión de cuentas',
            ];
        }
        if ($context->tienePermiso('roles.gestionar')) {
            $kpis[] = [
                'label'       => 'Roles',
                'value'       => '—',
                'icon'        => 'bi-shield-lock',
                'color'       => 'secondary',
                'url'         => '/admin/administracion/roles',
                'description' => 'RBAC',
            ];
        }
        if ($context->tienePermiso('administracion.ver')) {
            $kpis[] = [
                'label'       => 'Ajustes',
                'value'       => '—',
                'icon'        => 'bi-sliders',
                'color'       => 'info',
                'url'         => '/admin/ajustes',
                'description' => 'Layout y tema',
            ];
        }
        // KPI informativo (sin permiso): siempre presente para una contribución válida.
        $kpis[] = [
            'label'       => 'Extensión',
            'value'       => '',
            'icon'        => 'bi-journal-text',
            'color'       => 'success',
            'url'         => '#',
            'description' => 'Registrar proveedores en config/dashboard.php',
        ];

        $quick = [];
        if ($context->tienePermiso('usuarios.gestionar')) {
            $quick[] = ['url' => '/admin/administracion/usuarios', 'icon' => 'bi-people', 'label' => 'Usuarios'];
        }
        if ($context->tienePermiso('roles.gestionar')) {
            $quick[] = ['url' => '/admin/administracion/roles', 'icon' => 'bi-key', 'label' => 'Roles'];
        }
        if ($context->tienePermiso('administracion.ver')) {
            $quick[] = ['url' => '/admin/ajustes', 'icon' => 'bi-gear', 'label' => 'Ajustes'];
        }

        return new DashboardContribution(
            kpis: $kpis,
            activityItems: $activity,
            quickAccess: $quick,
            statusBlock: [
                'badge'     => 'OK',
                'badgeTone' => 'success',
                'lines'     => [
                    ['text' => 'Plataforma base operativa.', 'tone' => 'muted'],
                    ['text' => 'Otros módulos pueden contribuir KPIs y actividad sin modificar DashboardController.', 'tone' => 'muted'],
                ],
            ]
        );
    }
}
