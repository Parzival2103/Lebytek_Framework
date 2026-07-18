<?php

declare(strict_types=1);

test('platform migrate.php source uses PackagePaths not ROOT_PATH for schema.sql', function (): void {
    $migrate = dirname(__DIR__, 2) . '/scripts/migrate.php';
    assert_true(is_readable($migrate), 'scripts/migrate.php must exist');
    $src = (string) file_get_contents($migrate);
    assert_true(
        str_contains($src, 'PackagePaths::schema'),
        'migrate.php must call PackagePaths::schema'
    );
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'migrate.php must not read schema.sql from consumer ROOT_PATH'
    );
});

test('platform seed.php source uses PackagePaths for schema files', function (): void {
    $seed = dirname(__DIR__, 2) . '/scripts/seed.php';
    assert_true(is_readable($seed), 'scripts/seed.php must exist');
    $src = (string) file_get_contents($seed);
    assert_true(str_contains($src, 'PackagePaths'), 'seed.php must reference PackagePaths');
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'seed.php must not read schema.sql from consumer ROOT_PATH'
    );
    assert_true(
        !str_contains($src, 'marketing_demo'),
        'framework seed.php must not reference marketing_demo'
    );
});
