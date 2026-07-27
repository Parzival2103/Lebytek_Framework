<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('framework ships no monorepo-era vps deploy scripts', function () use ($root): void {
    $found = glob($root . '/scripts/vps-deploy-*.sh') ?: [];
    $names = array_map('basename', $found);
    assert_same([], $names, 'scripts/vps-deploy-*.sh must not exist: ' . implode(', ', $names));
});

test('no shell script pins the frozen backoffice branch', function () use ($root): void {
    foreach (glob($root . '/scripts/*.sh') ?: [] as $script) {
        $src = (string) file_get_contents($script);
        assert_true(
            !str_contains($src, 'feature/backoffice-api-integration'),
            basename($script) . ' must not pin feature/backoffice-api-integration'
        );
    }
});

test('no shell script wipes a site directory before repopulating it', function () use ($root): void {
    foreach (glob($root . '/scripts/*.sh') ?: [] as $script) {
        $src = (string) file_get_contents($script);
        assert_true(
            !str_contains($src, "-exec rm -rf {} +"),
            basename($script) . ' must not bulk-delete a deployed site directory'
        );
    }
});
