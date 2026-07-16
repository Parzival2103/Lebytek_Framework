<?php
// tests/Marketing/LandingVariantSelectionTest.php
declare(strict_types=1);

test('LandingController usa assigner no LANDING_VARIANT por request', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true(str_contains($src, 'LandingExperimentAssigner'), 'inyecta assigner');
    assert_true(str_contains($src, 'AssignInput'), 'usa DTO no Request en assigner');
    assert_true(str_contains($src, "query('variant'"), 'acepta ?variant= para force-preview');
    assert_true(!str_contains($src, "EnvLoader::get('LANDING_VARIANT'"), 'ya no selecciona por env');
    assert_true(str_contains($src, "'publico/landing_v2'") || str_contains($src, 'landing_v2'), 'conserva shell v2');
    assert_true(str_contains($src, "'publico/landing'") || str_contains($src, 'landing'), 'conserva shell v1');
});

test('LandingController conserva ?compras= y no llama Request::isSecure', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true(str_contains($src, "query('compras'"), 'conserva el flag ?compras=');
    assert_true(!str_contains($src, '->isSecure('), 'no usa Request::isSecure (no existe)');
});

test('partials v1 exponen data-section para metrics', function (): void {
    foreach (['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'] as $id) {
        $file = $id === 'lead_form' ? '_lead_form.php' : "_{$id}.php";
        $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/partials/' . $file);
        assert_true(str_contains($src, 'data-section="' . $id . '"'), $file);
    }
});

test('config/app.php conserva landing_variant como seed hint', function (): void {
    $config = require ROOT_PATH . '/config/app.php';
    assert_true(array_key_exists('landing_variant', $config), 'clave presente');
});

test('LandingSectionRenderer es el único mapa de secciones (Anti-deuda §G)', function (): void {
    $rendererSrc = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Marketing/LandingSectionRenderer.php');
    assert_true(str_contains($rendererSrc, "'lead_form' =>"), 'define el mapa único');

    foreach (['landing.php', 'landing_v2.php'] as $view) {
        $viewSrc = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/' . $view);
        assert_true(!str_contains($viewSrc, "ViewHelper::render('publico/partials"), $view . ' no duplica el mapa de partials');
    }
});
