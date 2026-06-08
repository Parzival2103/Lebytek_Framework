<?php

declare(strict_types=1);

use App\Application\Install\ModuleRegistry;
use App\Application\Install\ManifestValidator;

test('Integridad: todos los .sql reales tienen dueño único en algún manifiesto', function (): void {
    $registry  = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $manifests = $registry->all();

    $migraciones = array_map('basename', glob(ROOT_PATH . '/database/migrations/*.sql') ?: []);
    $seeds       = array_map('basename', glob(ROOT_PATH . '/database/seeds/*.sql') ?: []);

    $crudFiles = glob(ROOT_PATH . '/config/cruds/*.json') ?: [];
    $cruds = array_map(static fn(string $f): string => basename($f, '.json'), $crudFiles);

    $errores = ManifestValidator::errores($manifests, [
        'migraciones' => array_values($migraciones),
        'seeds'       => array_values($seeds),
        'cruds'       => array_values($cruds),
    ]);

    assert_same([], $errores);
});

test('Integridad: core existe y es obligatorio', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $core = $registry->get('core');
    assert_true($core !== null);
    assert_true($core->obligatorio);
});
