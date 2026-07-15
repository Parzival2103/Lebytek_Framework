<?php
// tests/Marketing/LandingVariantRegistryTest.php
declare(strict_types=1);

use App\Domain\Marketing\LandingVariantRegistry;

test('LandingVariantRegistry carga manifiestos y mapea reveal ids', function (): void {
    /** @var array{catalog:list<string>,reveal_id_map:array<string,string>,variants:array<string,array>} $cfg */
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $reg = new LandingVariantRegistry($cfg);
    assert_true($reg->get('v1') !== null, 'v1');
    assert_true($reg->get('v2') !== null, 'v2');
    assert_same('testimonials', $reg->revealId('testimonios'));
    assert_same('cta', $reg->revealId('lead_form'));
    assert_true(in_array('v1', $reg->activeSlugs(), true), 'v1 active');
});
