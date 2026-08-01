<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('composer.json declares platform version semver', function () use ($root): void {
    $composerPath = $root . '/composer.json';
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_true(
        isset($data['version']) && is_string($data['version']) && $data['version'] !== '',
        'composer.json must declare a non-empty "version" field (semver without v prefix). Action: sync three files semver — add "version": "1.2.1" aligned with tag v1.2.1'
    );
});

test('harness config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $rootConfig = require $root . '/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same(
        $composer['version'],
        $rootConfig['version'] ?? null,
        'config/app.php version must match composer.json. Action: sync three files semver'
    );
});

test('skeleton config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $skelConfig = require $root . '/skeleton/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same(
        $composer['version'],
        $skelConfig['version'] ?? null,
        'skeleton/config/app.php version must match composer.json. Action: sync three files semver'
    );
});
