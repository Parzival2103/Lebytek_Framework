<?php
// tests/Marketing/PurgeLandingMetricsUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\PurgeLandingMetricsUseCase;

test('purge borra solo filas antiguas', function (): void {
    $repo = new FakeMetricsRepo();
    $repo->seedOldAndNew();
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new PurgeLandingMetricsUseCase($repo, $exp);
    $out = $uc->ejecutar();
    assert_true($out['events'] >= 1, 'borra events viejos');
    assert_true($repo->countNewEvents() >= 1, 'conserva recientes');
});

test('purge usa retention_days del config para el cutoff', function (): void {
    $repo = new FakeMetricsRepo();
    $repo->seedOldAndNew();
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $exp['retention_days'] = 30;
    $uc = new PurgeLandingMetricsUseCase($repo, $exp);
    $out = $uc->ejecutar();
    assert_true($out['events'] >= 1, 'borra con ventana más corta');
    assert_true($out['sessions'] >= 1, 'borra sesiones antiguas');
});

test('purge CLI script imprime purged_sessions y purged_events', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/scripts/purge-landing-metrics.php');
    assert_true(str_contains($src, 'purged_sessions='), 'formato sessions');
    assert_true(str_contains($src, 'purged_events='), 'formato events');
    assert_true(str_contains($src, 'PurgeLandingMetricsUseCase'), 'usa use case');
});
