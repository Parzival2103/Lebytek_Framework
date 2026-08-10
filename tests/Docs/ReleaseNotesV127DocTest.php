<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('v1.2.7 release notes document REL-C1 content and skipped tags', function () use ($root): void {
    $path = $root . '/docs/release/v1.2.7.md';
    assert_true(is_file($path), 'missing docs/release/v1.2.7.md — create release notes per REL-C1 spec');
    $src = (string) file_get_contents($path);

    foreach ([
        'AuthZ',
        'states',
        'invoicing',
        'OFF',
        'PHP',
        '8.2',
        'v1.2.4',
        'v1.2.5',
        'v1.2.6',
        'hardening',
        '#95',
        '#100',
    ] as $needle) {
        assert_true(str_contains($src, $needle), "release notes must mention {$needle} (REL-C1 U1/U5)");
    }

    assert_true(
        str_contains($src, '1.2.8'),
        'release notes must state M3/M4 ship in 1.2.8+ not 1.2.4/1.2.5'
    );
});

test('M4 health plan retargets semver to 1.2.8 after REL-C1', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/superpowers/plans/2026-08-05-audit-api-health-public.md');
    assert_true(str_contains($src, '1.2.8'), 'M4 plan must target 1.2.8+ after REL-C1');
    assert_true(
        !preg_match('/tag `v1\.2\.4`/i', $src) || str_contains($src, 'skip'),
        'obsolete v1.2.4 tag target must be annotated as skipped or replaced'
    );
});
