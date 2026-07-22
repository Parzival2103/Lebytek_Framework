<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\PackagePaths;

test('PackagePaths root points at package root containing composer.json named lebytek/framework', function (): void {
    $root = PackagePaths::root();
    assert_true(is_dir($root), 'package root must exist');
    $composer = $root . '/composer.json';
    assert_true(is_readable($composer), 'composer.json must exist at package root');
    $data = json_decode((string) file_get_contents($composer), true);
    assert_same('lebytek/framework', $data['name'] ?? null);
});

test('PackagePaths schema resolves platform schema.sql inside the package', function (): void {
    $schema = PackagePaths::schema('schema.sql');
    assert_true(is_readable($schema), 'schema.sql must be readable via PackagePaths');
    assert_true(
        str_contains($schema, DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'schema.sql')
        || str_contains($schema, '/database/schema/schema.sql'),
        'path must include database/schema/schema.sql'
    );
});

test('PackagePaths moduleSchema resolves integrations module under package', function (): void {
    $path = PackagePaths::moduleSchema('integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must exist in package modules');
});

test('PackagePaths seedsDir is a directory under the package', function (): void {
    $dir = PackagePaths::seedsDir();
    assert_true(is_dir($dir), 'seeds dir must exist');
});

test('PackagePaths resolveDataFile prefers package for platform module SQL', function (): void {
    $path = PackagePaths::resolveDataFile('database/schema/modules/integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must resolve');
    assert_true(
        str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', PackagePaths::root())),
        'platform SQL must resolve inside the package root'
    );
});

test('PackagePaths resolveDataFile falls back to ROOT_PATH for consumer-only files', function (): void {
    $rel = 'database/schema/modules/__consumer_only_probe__.sql';
    $probe = ROOT_PATH . '/' . $rel;
    $dir = dirname($probe);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($probe, "-- probe\n");
    try {
        $resolved = PackagePaths::resolveDataFile($rel);
        assert_same(str_replace('\\', '/', $probe), str_replace('\\', '/', $resolved));
    } finally {
        @unlink($probe);
    }
});
