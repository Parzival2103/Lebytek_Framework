<?php
// tests/Marketing/LandingVariantsConfigTest.php
declare(strict_types=1);

test('landing_variants.php define catalogo y armas v1/v2 active', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    assert_true(isset($cfg['catalog'], $cfg['variants']), 'estructura base');
    foreach (['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'] as $id) {
        assert_true(in_array($id, $cfg['catalog'], true), "catalog contiene {$id}");
    }
    assert_true(isset($cfg['variants']['v1'], $cfg['variants']['v2']), 'v1 y v2 presentes');
    assert_same('active', $cfg['variants']['v1']['status']);
    assert_same('active', $cfg['variants']['v2']['status']);
    assert_same('v1', $cfg['variants']['v1']['shell']);
    assert_same('v2', $cfg['variants']['v2']['shell']);
    assert_true(isset($cfg['variants']['v1']['seo']['title'], $cfg['variants']['v1']['seo']['description']), 'seo v1');
    assert_true(isset($cfg['variants']['v2']['seo']['title'], $cfg['variants']['v2']['seo']['description']), 'seo v2');
});

test('landing_experiments.php expone defaults de score y seed weights', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    assert_same(30, (int) $cfg['cookie_ttl_days']);
    assert_same(14, (int) $cfg['score_window_days']);
    assert_same(0.35, (float) $cfg['w_eng']);
    assert_same(0.65, (float) $cfg['w_conv']);
    assert_same(50, (int) $cfg['min_sessions']);
    assert_true(is_callable($cfg['seed_weight_defaults'] ?? null) || isset($cfg['seed_weight_defaults']), 'seed helper');
    $seeds = is_callable($cfg['seed_weight_defaults'])
        ? $cfg['seed_weight_defaults']()
        : $cfg['seed_weight_defaults'];
    assert_true(isset($seeds['v1'], $seeds['v2']), 'seeds v1/v2');
    assert_true((float) $seeds['v1'] > 0 && (float) $seeds['v2'] > 0, 'ambos exploratorios > 0');
});

test('.env.example documenta LANDING_VARIANT solo como seed hint', function (): void {
    $env = (string) file_get_contents(ROOT_PATH . '/.env.example');
    assert_true(str_contains($env, 'LANDING_VARIANT'), 'clave presente');
    assert_true(
        str_contains(strtolower($env), 'seed') || str_contains(strtolower($env), 'bootstrap') || str_contains(strtolower($env), 'weight'),
        'comenta que no selecciona trafico'
    );
});
