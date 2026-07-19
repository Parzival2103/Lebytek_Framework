<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('FPS publication manifest checklist exists', function () use ($root): void {
    $path = $root . '/docs/superpowers/FPS-publication-manifest-checklist.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'PackageAutoloadBoundary'));
    assert_true(str_contains($src, 'SkeletonPurity'));
    assert_true(str_contains($src, 'FrameworkRootNotPortal'));
    assert_true(str_contains($src, 'NO PRODUCTION EXECUTION'));
});

test('CUTOVER runbook exists and forbids implicit merge to main', function () use ($root): void {
    $path = $root . '/docs/CUTOVER-PORTAL.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'rollback'));
    assert_true(str_contains($src, 'feature/backoffice-api-integration'));
    assert_true(str_contains($src, 'explicit user order'));
});
