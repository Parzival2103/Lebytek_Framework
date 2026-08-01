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

test('framework root .env.example does not ship Portal or Marketing env keys', function () use ($root): void {
    $path = $root . '/.env.example';
    assert_true(is_readable($path), 'root .env.example must exist');
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $forbiddenPrefixes = ['MKT_', 'LEBYTEK_API_', 'WAAPI_PORTAL_'];
    foreach ($lines as $lineNum => $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (!str_contains($trimmed, '=')) {
            continue;
        }
        $key = trim(explode('=', $trimmed, 2)[0]);
        foreach ($forbiddenPrefixes as $prefix) {
            assert_true(
                !str_starts_with($key, $prefix),
                ".env.example line " . ($lineNum + 1) . ": key «{$key}» uses forbidden prefix «{$prefix}». "
                . 'Action: remove Portal/Marketing vars from root .env.example; see Lebytek_Portal/.env.example'
            );
        }
    }
});
