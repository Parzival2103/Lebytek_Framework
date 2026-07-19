<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('ARCHITECTURE-CONSUMER forbids deploying Framework root', function () use ($root): void {
    $path = $root . '/docs/ARCHITECTURE-CONSUMER.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'Never edit `vendor/`'));
    assert_true(str_contains($src, 'never deploy Framework root'));
});

test('TENANTS distinguishes Framework Portal and customer skeleton', function () use ($root): void {
    $path = $root . '/docs/TENANTS.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'Lebytek_Portal'));
    assert_true(str_contains($src, 'never start a customer project by cloning'));
});

test('SCHEMA-OWNERSHIP assigns Payments to Framework and Marketing to Portal', function () use ($root): void {
    $path = $root . '/docs/database/SCHEMA-OWNERSHIP.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'payments'));
    assert_true(str_contains($src, 'Marketing'));
    assert_true(str_contains($src, 'Portal'));
    assert_true(str_contains($src, 'PackagePaths'));
});

test('ASSETS-PLATFORM lists canonical platform asset files', function () use ($root): void {
    $path = $root . '/docs/ASSETS-PLATFORM.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'app.css'));
    assert_true(str_contains($src, 'crud-engine.js'));
    assert_true(str_contains($src, 'public/assets/'));
});
