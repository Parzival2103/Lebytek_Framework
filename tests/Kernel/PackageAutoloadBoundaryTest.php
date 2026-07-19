<?php

declare(strict_types=1);

$composerPath = dirname(__DIR__, 2) . '/composer.json';

test('framework package composer.json is readable JSON', function () use ($composerPath): void {
    assert_true(is_readable($composerPath), 'composer.json must be readable');
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_true(is_array($data), 'composer.json must decode to array');
});

test('framework package name is lebytek/framework', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_same('lebytek/framework', $data['name'] ?? null);
});

test('framework package autoloads Lebytek\\Framework\\ from src/', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    $psr4 = $data['autoload']['psr-4'] ?? [];
    assert_same('src/', $psr4['Lebytek\\Framework\\'] ?? null);
});

test('framework package does not autoload App\\', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    $psr4 = $data['autoload']['psr-4'] ?? [];
    assert_true(!array_key_exists('App\\', $psr4), 'App\\ must not be in package autoload');
});
