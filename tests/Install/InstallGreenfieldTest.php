<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\PackagePaths;

test('greenfield platform schema resolves from package not consumer copy requirement', function (): void {
    $schema = PackagePaths::schema('schema.sql');
    assert_true(is_readable($schema), 'package schema.sql must be readable');
    assert_true(
        str_starts_with(str_replace('\\', '/', $schema), str_replace('\\', '/', PackagePaths::root())),
        'schema must live under package root'
    );
    $sql = (string) file_get_contents($schema);
    assert_true(str_contains($sql, 'DATOS INICIALES'), 'consolidated bootstrap must include DATOS INICIALES');
    assert_true(str_contains($sql, 'admin@sistema.local'), 'consolidated bootstrap must seed admin user');
});

test('greenfield consumer can resolve crud-engine bootstrap_sql via PackagePaths', function (): void {
    $path = PackagePaths::resolveDataFile('database/schema/modules/crud-engine.sql');
    assert_true(is_readable($path), 'crud-engine bootstrap must resolve from package');
});

test('database/seeds active directory has no loose platform SQL files', function (): void {
    $activeSeeds = glob(PackagePaths::root() . '/database/seeds/*.sql') ?: [];
    assert_same([], $activeSeeds, 'platform seeds consolidated into schema.sql; no loose *.sql in database/seeds/');
});

test('legacy seeds directory is not referenced by Installer manifests', function (): void {
    $modulesDir = ROOT_PATH . '/config/modules';
    foreach (glob($modulesDir . '/*.php') ?: [] as $file) {
        $cfg = require $file;
        if (!is_array($cfg)) {
            continue;
        }
        foreach ($cfg['seeds'] ?? [] as $seedFile) {
            assert_true(
                !str_contains((string) $seedFile, 'seeds_legacy'),
                "manifest must not reference seeds_legacy: {$file}"
            );
        }
    }
});
