<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('PACKAGE-ROOT documents declared semver must match published git tag', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/PACKAGE-ROOT.md');
    assert_true(str_contains($src, 'ReleaseTagPublishedTest'), 'PACKAGE-ROOT must reference tag gate test');
    assert_true(
        str_contains($src, 'tag') && str_contains($src, 'composer.json'),
        'PACKAGE-ROOT must state composer.json version requires matching git tag for releases'
    );
});
