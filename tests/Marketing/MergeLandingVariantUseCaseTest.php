<?php
// tests/Marketing/MergeLandingVariantUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\MergeLandingVariantUseCase;
use App\Domain\Marketing\LandingVariantRegistry;

test('merge aplica seo del manifiesto y shallow override', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $cfg['variants']['v1']['copy_overrides'] = [
        'hero' => ['titulo' => 'Override Title'],
    ];
    $reg = new LandingVariantRegistry($cfg);
    $uc = new MergeLandingVariantUseCase($reg);
    $out = $uc->merge('v1', [
        'hero' => ['titulo' => 'Original', 'subtitulo' => 'Sub'],
        'faq' => ['items' => []],
    ]);
    assert_same('Override Title', $out['bloques']['hero']['titulo']);
    assert_same('Sub', $out['bloques']['hero']['subtitulo']);
    assert_same($cfg['variants']['v1']['seo']['title'], $out['seo']['title']);
    assert_true(in_array('hero', $out['sections'], true), 'hero enabled');
});

test('merge omite secciones disabled', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    foreach ($cfg['variants']['v1']['sections'] as &$s) {
        if ($s['id'] === 'faq') {
            $s['enabled'] = false;
        }
    }
    unset($s);
    $uc = new MergeLandingVariantUseCase(new LandingVariantRegistry($cfg));
    $out = $uc->merge('v1', []);
    assert_true(!in_array('faq', $out['sections'], true), 'faq oculto');
});
