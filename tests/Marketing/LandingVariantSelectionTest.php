<?php
// tests/Marketing/LandingVariantSelectionTest.php
declare(strict_types=1);

test('LandingController implementa la selección de variante v1/v2 por flag y override', function (): void {
    $src = file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true($src !== false, 'archivo existe');
    assert_true(str_contains($src, "EnvLoader::get('LANDING_VARIANT'"), 'lee el flag de entorno');
    assert_true(str_contains($src, "query('landing'"), 'soporta override por query ?landing=');
    assert_true(str_contains($src, "'publico/landing_v2'"), 'referencia la vista v2');
    assert_true(str_contains($src, "'publico/layout_v2'"), 'referencia el layout v2');
    assert_true(str_contains($src, "'publico/landing'"), 'conserva la vista v1');
    assert_true(str_contains($src, "'publico/layout'"), 'conserva el layout v1');
});

test('config/app.php expone landing_variant con default v1', function (): void {
    $config = require ROOT_PATH . '/config/app.php';
    assert_true(array_key_exists('landing_variant', $config), 'clave landing_variant presente');
    assert_true($config['landing_variant'] === 'v1' || is_string($config['landing_variant']), 'valor string (default v1 sin env)');
});

test('.env.example documenta LANDING_VARIANT', function (): void {
    $env = file_get_contents(ROOT_PATH . '/.env.example');
    assert_true($env !== false, 'archivo existe');
    assert_true(str_contains($env, 'LANDING_VARIANT'), 'documenta el flag');
});
