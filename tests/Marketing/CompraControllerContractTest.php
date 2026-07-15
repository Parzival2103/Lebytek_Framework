<?php

declare(strict_types=1);

test('CompraController enforces CSRF, rate limit, and purchasable slugs', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/CompraController.php');

    assert_true(str_contains($src, 'verifyCsrf'), 'POST checkout must verify CSRF');
    assert_true(str_contains($src, "compra_posts"), 'rate-limit session key');
    assert_true(str_contains($src, '>= 10'), 'max 10 posts per hour window');
    assert_true(str_contains($src, "3600"), '1h window');
    assert_true(str_contains($src, "'starter'"), 'starter purchasable');
    assert_true(str_contains($src, "'business'"), 'business purchasable');
    preg_match('/private const PURCHASABLE_SLUGS = \[(.*?)\];/s', $src, $slugMatch);
    $purchasableSlugs = $slugMatch[1] ?? '';
    assert_true(! str_contains($purchasableSlugs, "'empresa'"), 'enterprise must not be in PURCHASABLE_SLUGS list');
    assert_true(str_contains($src, '/comprar/orden/'), 'redirect to transfer view by public_id');
    assert_true(str_contains($src, '/transferencia'), 'transfer path suffix');
});

test('compra routes wire CompraController submit and transferencia', function (): void {
    $routes = (string) file_get_contents(ROOT_PATH.'/routes/marketing.php');
    assert_true(str_contains($routes, "CompraController"), 'controller bound');
    assert_true(str_contains($routes, '/comprar/{slug}'), 'checkout path');
    assert_true(str_contains($routes, '/comprar/orden/{publicId}/transferencia'), 'transfer path');
});
