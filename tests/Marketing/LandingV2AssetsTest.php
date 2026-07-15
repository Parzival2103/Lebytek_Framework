<?php
// tests/Marketing/LandingV2AssetsTest.php
declare(strict_types=1);

test('landing_v2.css existe con keyframes, breakpoints y clases de reveal/billing', function (): void {
    $css = file_get_contents(ROOT_PATH . '/public/assets/publico/landing_v2.css');
    assert_true($css !== false, 'archivo existe');
    foreach (['@keyframes dotMove', '@keyframes hubPulse', '@keyframes floatY', '@keyframes drift',
              'max-width: 860px', 'max-width: 560px', 'prefers-reduced-motion',
              '.lb-reveal', '.lb-reveal--on', '.lb-billing-btn'] as $needle) {
        assert_true(str_contains($css, $needle), "css contiene {$needle}");
    }
});

test('landing_v2.js existe con reveal, acordeón, billing y merge de empresa', function (): void {
    $js = file_get_contents(ROOT_PATH . '/public/assets/publico/landing_v2.js');
    assert_true($js !== false, 'archivo existe');
    foreach (['IntersectionObserver', 'data-reveal-id', 'lb-reveal--on',
              'data-faq-toggle', 'lb-faq-panel',
              'data-period', 'data-monthly', 'data-compra-', 'data-empresa-merge'] as $needle) {
        assert_true(str_contains($js, $needle), "js referencia {$needle}");
    }
});
