<?php

declare(strict_types=1);

$skeleton = dirname(__DIR__, 2) . '/skeleton';

test('skeleton does not ship App Domain Marketing', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/app/Domain/Marketing'), 'no Domain/Marketing');
    assert_true(!is_dir($skeleton . '/app/Application/Marketing'), 'no Application/Marketing');
    assert_true(!is_dir($skeleton . '/app/Infrastructure/Marketing'), 'no Infrastructure/Marketing');
    assert_true(!is_dir($skeleton . '/app/Presentation/Controllers/Publico'), 'no Controllers/Publico');
    assert_true(!is_dir($skeleton . '/app/Presentation/Views/publico'), 'no Views/publico');
});

test('skeleton does not ship marketing schema or mkt configs', function () use ($skeleton): void {
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing_demo.sql'));
    assert_true(!is_file($skeleton . '/config/modules/marketing.php'));
    assert_true(!is_file($skeleton . '/routes/marketing.php'));
    foreach (glob($skeleton . '/config/cruds/mkt_*.json') ?: [] as $_) {
        assert_true(false, 'skeleton must not contain config/cruds/mkt_*.json');
    }
});

test('skeleton does not ship Marketing tests', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/tests/Marketing'));
});

test('skeleton container.php does not reference App Marketing classes', function () use ($skeleton): void {
    $src = (string) file_get_contents($skeleton . '/config/container.php');
    assert_true(
        !str_contains($src, 'App\\Infrastructure\\Marketing')
        && !str_contains($src, 'App\\Domain\\Marketing')
        && !str_contains($src, 'App\\Application\\Marketing'),
        'container.php must not hard-bind Marketing classes'
    );
});

test('skeleton vertical keeps marketing and payments OFF', function () use ($skeleton): void {
    $vertical = require $skeleton . '/config/vertical.php';
    assert_same(false, $vertical['modules']['marketing'] ?? null);
    assert_same(false, $vertical['modules']['payments'] ?? null);
});

test('skeleton does not ship LebytekApi client or env vars', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/app/Infrastructure/Integrations/LebytekApi'));
    $envExample = (string) file_get_contents($skeleton . '/.env.example');
    assert_true(!str_contains($envExample, 'LEBYTEK_API_'), '.env.example must not ship LEBYTEK_API_*');
});

test('skeleton does not ship marketing SQL modules', function () use ($skeleton): void {
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing_demo.sql'));
});

test('skeleton does not ship publico landing assets', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/public/assets/publico'));
});

test('skeleton does not duplicate platform seeds as SoT', function () use ($skeleton): void {
    $seedFiles = glob($skeleton . '/database/seeds/*.sql') ?: [];
    assert_true(
        $seedFiles === [],
        'skeleton database/seeds must not ship platform *.sql copies (use package seeds)'
    );
});

test('skeleton ships required platform UI assets', function () use ($skeleton): void {
    $required = [
        'public/assets/css/app.css',
        'public/assets/css/lebytek-ui.css',
        'public/assets/css/crud-engine.css',
        'public/assets/js/app.js',
        'public/assets/js/crud-engine.js',
        'public/assets/js/calendar.js',
        'public/assets/js/avatar-manager.js',
        'public/assets/js/reportes-builder.js',
        'public/assets/icons/app-icon.svg',
        'public/assets/images/logo.png',
    ];
    foreach ($required as $rel) {
        assert_true(is_readable($skeleton . '/' . $rel), "missing platform asset: {$rel}");
    }
});
