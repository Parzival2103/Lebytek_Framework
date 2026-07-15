<?php
// tests/Marketing/LandingMetricsAssetTest.php
declare(strict_types=1);

test('landing_metrics.js existe y layouts lo incluyen', function (): void {
    assert_true(is_file(ROOT_PATH.'/public/assets/publico/landing_metrics.js'), 'asset');
    $js = (string) file_get_contents(ROOT_PATH.'/public/assets/publico/landing_metrics.js');
    assert_true(!str_contains($js, 'lead_submit'), 'cliente no emite lead_submit');
    assert_true(!str_contains($js, 'document.cookie'), 'no lee cookies');
    foreach (['layout.php','layout_v2.php'] as $f) {
        $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Views/publico/'.$f);
        assert_true(str_contains($src, 'landing_metrics.js'), $f);
        assert_true(str_contains($src, '__LB_METRICS__'), $f.' bootstrap');
    }
});

test('routes/marketing.php registra /marketing/collect sin CsrfMiddleware', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/routes/marketing.php');
    assert_true(str_contains($src, "post('/marketing/collect'"), 'ruta registrada');

    // La línea de la ruta collect no debe traer el arreglo de middlewares CSRF.
    $lines = explode("\n", $src);
    $collectLine = '';
    foreach ($lines as $line) {
        if (str_contains($line, "post('/marketing/collect'")) {
            $collectLine = $line;
            break;
        }
    }
    assert_true($collectLine !== '', 'linea de ruta encontrada');
    assert_true(!str_contains($collectLine, 'CsrfMiddleware'), 'collect es CSRF-exempt');
});
