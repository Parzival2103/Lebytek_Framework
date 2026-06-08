<?php

declare(strict_types=1);

use App\Application\Install\ManifestValidator;
use App\Application\Install\ModuleManifest;

function mv_manifest(array $over): ModuleManifest
{
    return ModuleManifest::fromArray(array_merge(
        ['clave' => 'x', 'nombre' => 'X', 'version' => '1.0.0'],
        $over
    ));
}

test('ManifestValidator: manifiestos consistentes no producen errores', function (): void {
    $manifests = [
        'core'  => mv_manifest(['clave' => 'core', 'obligatorio' => true, 'seeds' => ['010.sql']]),
        'crud'  => mv_manifest(['clave' => 'crud', 'requiere' => ['core'], 'migraciones' => ['m1.sql'], 'cruds' => ['demo_x']]),
    ];
    $ctx = ['migraciones' => ['m1.sql'], 'seeds' => ['010.sql'], 'cruds' => ['demo_x']];
    assert_same([], ManifestValidator::errores($manifests, $ctx));
});

test('ManifestValidator: archivo en disco sin dueño es huérfano', function (): void {
    $manifests = ['core' => mv_manifest(['clave' => 'core'])];
    $ctx = ['migraciones' => ['huerfana.sql'], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: archivo con doble dueño se reporta', function (): void {
    $manifests = [
        'a' => mv_manifest(['clave' => 'a', 'migraciones' => ['m1.sql']]),
        'b' => mv_manifest(['clave' => 'b', 'migraciones' => ['m1.sql']]),
    ];
    $ctx = ['migraciones' => ['m1.sql'], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: dependencia inexistente se reporta', function (): void {
    $manifests = ['crud' => mv_manifest(['clave' => 'crud', 'requiere' => ['core']])];
    $ctx = ['migraciones' => [], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});

test('ManifestValidator: crud declarado inexistente se reporta', function (): void {
    $manifests = ['crud' => mv_manifest(['clave' => 'crud', 'cruds' => ['demo_x']])];
    $ctx = ['migraciones' => [], 'seeds' => [], 'cruds' => []];
    $errores = ManifestValidator::errores($manifests, $ctx);
    assert_same(1, count($errores));
});
