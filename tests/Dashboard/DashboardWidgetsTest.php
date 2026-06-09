<?php
declare(strict_types=1);

use App\Application\UseCases\Dashboard\BuildDashboardViewModelUseCase;
use App\Domain\Dashboard\DashboardBuildContext;
use App\Domain\Dashboard\DashboardContribution;
use App\Domain\Interfaces\DashboardContributionProviderInterface;

test('BuildDashboardViewModelUseCase fusiona widgets de los proveedores', function (): void {
    $provider = new class implements DashboardContributionProviderInterface {
        public function priority(): int { return 50; }
        public function contribute(DashboardBuildContext $c): DashboardContribution {
            return new DashboardContribution(
                kpis: [], activityItems: [], quickAccess: [], statusBlock: null,
                widgets: [['partial' => 'dashboard/calendar_mini', 'data' => ['key' => 'demo_citas']]]
            );
        }
    };
    $useCase = new BuildDashboardViewModelUseCase([$provider]);
    $vm = $useCase->execute(new DashboardBuildContext(1, [], []));
    assert_same('dashboard/calendar_mini', $vm->widgets[0]['partial'] ?? null, 'widget fusionado');
    assert_same('demo_citas', $vm->widgets[0]['data']['key'] ?? null, 'datos del widget');
});

test('DashboardContribution::vacia no aporta widgets', function (): void {
    assert_same([], DashboardContribution::vacia()->widgets, 'vacía => sin widgets');
});

test('BuildDashboardViewModelUseCase sin proveedores deja widgets vacío', function (): void {
    $vm = (new BuildDashboardViewModelUseCase([]))->execute(new DashboardBuildContext(null, [], []));
    assert_same([], $vm->widgets, 'sin proveedores => sin widgets');
});
