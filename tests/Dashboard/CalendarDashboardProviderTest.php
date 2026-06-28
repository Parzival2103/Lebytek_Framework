<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Domain\Dashboard\DashboardBuildContext;
use Lebytek\Framework\Infrastructure\Dashboard\CalendarDashboardProvider;

function cal_provider(): CalendarDashboardProvider
{
    return new CalendarDashboardProvider(new CalendarConfigLoader(new CalendarConfigValidator()));
}

test('CalendarDashboardProvider devuelve vacía sin permiso del recurso', function (): void {
    $contrib = cal_provider()->contribute(new DashboardBuildContext(1, [], []));
    assert_same([], $contrib->widgets, 'sin permiso => sin widget');
});

test('CalendarDashboardProvider aporta widget calendar_mini con permiso', function (): void {
    $contrib = cal_provider()->contribute(new DashboardBuildContext(1, ['demo_citas.ver'], []));
    assert_same('dashboard/calendar_mini', $contrib->widgets[0]['partial'] ?? null, 'widget presente');
    assert_same('demo_citas', $contrib->widgets[0]['data']['key'] ?? null, 'apunta al calendario');
    assert_same('/admin/calendario/demo_citas', $contrib->widgets[0]['data']['url'] ?? null, 'url al calendario');
});
