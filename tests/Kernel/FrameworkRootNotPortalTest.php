<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('framework root does not ship Marketing domain', function () use ($root): void {
    assert_true(!is_dir($root . '/app/Domain/Marketing'));
    assert_true(!is_dir($root . '/app/Presentation/Views/publico'));
    assert_true(!is_file($root . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($root . '/routes/marketing.php'));
});

test('framework root vertical keeps marketing OFF', function () use ($root): void {
    $vertical = require $root . '/config/vertical.php';
    assert_same(false, $vertical['modules']['marketing'] ?? null);
});

test('framework PACKAGE-ROOT doc exists and forbids deploy', function () use ($root): void {
    $doc = $root . '/docs/PACKAGE-ROOT.md';
    assert_true(is_readable($doc));
    $src = (string) file_get_contents($doc);
    assert_true(
        str_contains($src, 'no deploy') || str_contains($src, 'no se despliega'),
        'must forbid deploy'
    );
});
