<?php

declare(strict_types=1);

use App\Domain\Dashboard\DashboardBuildContext;
use App\Infrastructure\Dashboard\DefaultPlatformDashboardProvider;

function dash_labels(array $items): array
{
    return array_values(array_map(static fn(array $i): string => (string) ($i['label'] ?? ''), $items));
}

test('Dashboard: con permiso de usuarios el KPI y quick "Usuarios" aparecen', function (): void {
    $ctx = new DashboardBuildContext(1, ['usuarios.gestionar'], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_true(in_array('Usuarios', dash_labels($c->kpis), true), 'KPI Usuarios presente');
    assert_true(in_array('Usuarios', dash_labels($c->quickAccess), true), 'Quick Usuarios presente');
    assert_true(!in_array('Roles', dash_labels($c->kpis), true), 'KPI Roles ausente sin permiso');
});

test('Dashboard: sin ningún permiso la contribución es válida y no rompe', function (): void {
    $ctx = new DashboardBuildContext(1, [], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_true(!in_array('Usuarios', dash_labels($c->kpis), true), 'sin permiso no hay KPI Usuarios');
    assert_true(!in_array('Roles', dash_labels($c->kpis), true), 'sin permiso no hay KPI Roles');
    assert_true(!in_array('Ajustes', dash_labels($c->kpis), true), 'sin permiso no hay KPI Ajustes');
    // "Extensión" (informativo, sin permiso) sigue presente => contribución válida
    assert_true(in_array('Extensión', dash_labels($c->kpis), true), 'KPI informativo presente');
    assert_same([], $c->quickAccess, 'sin permisos no hay accesos rápidos');
});

test('Dashboard: con todos los permisos se ve lo mismo que hoy (sin regresión)', function (): void {
    $ctx = new DashboardBuildContext(1, ['usuarios.gestionar', 'roles.gestionar', 'administracion.ver'], []);
    $c = (new DefaultPlatformDashboardProvider())->contribute($ctx);

    assert_same(['Usuarios', 'Roles', 'Ajustes', 'Extensión'], dash_labels($c->kpis));
    assert_same(['Usuarios', 'Roles', 'Ajustes'], dash_labels($c->quickAccess));
});
