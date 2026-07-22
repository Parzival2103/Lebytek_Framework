<?php

declare(strict_types=1);

test('Installer.php source resolves data files via PackagePaths::resolveDataFile', function (): void {
    $file = dirname(__DIR__, 2) . '/src/Application/Install/Installer.php';
    assert_true(is_readable($file), 'Installer.php must exist');
    $src = (string) file_get_contents($file);
    assert_true(
        str_contains($src, 'PackagePaths::resolveDataFile'),
        'Installer must call PackagePaths::resolveDataFile'
    );
});

test('Installer.php defines resolveMigrationFile and resolveSeedFile helpers', function (): void {
    $file = dirname(__DIR__, 2) . '/src/Application/Install/Installer.php';
    $src = (string) file_get_contents($file);
    assert_true(str_contains($src, 'resolveMigrationFile'), 'Installer must define resolveMigrationFile');
    assert_true(str_contains($src, 'resolveSeedFile'), 'Installer must define resolveSeedFile');
});
