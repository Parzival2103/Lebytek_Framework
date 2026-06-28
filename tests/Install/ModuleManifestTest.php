<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Install\ModuleManifest;
use Lebytek\Framework\Domain\Exceptions\InstallerException;

test('ModuleManifest::fromArray expone propiedades y aplica defaults', function (): void {
    $m = ModuleManifest::fromArray([
        'clave'       => 'crud-engine',
        'nombre'      => 'CRUD Engine',
        'version'     => '1.0.0',
        'requiere'    => ['core'],
        'migraciones' => ['a.sql'],
    ]);
    assert_same('crud-engine', $m->clave);
    assert_same('1.0.0', $m->version);
    assert_same(false, $m->obligatorio);
    assert_same(['core'], $m->requiere);
    assert_same(['a.sql'], $m->migraciones);
    assert_same([], $m->seeds);
});

test('ModuleManifest::fromArray expone bootstrapSql opcional', function (): void {
    $m = ModuleManifest::fromArray([
        'clave'         => 'crud-engine',
        'version'       => '1.0.0',
        'bootstrap_sql' => 'database/schema/modules/crud-engine.sql',
    ]);
    assert_same('database/schema/modules/crud-engine.sql', $m->bootstrapSql);
});

test('ModuleManifest::fromArray bootstrapSql null si falta o vacío', function (): void {
    $m = ModuleManifest::fromArray(['clave' => 'core', 'version' => '1.0.0']);
    assert_null($m->bootstrapSql);
});

test('ModuleManifest::fromArray exige clave', function (): void {
    assert_throws(InstallerException::class, function (): void {
        ModuleManifest::fromArray(['version' => '1.0.0']);
    });
});

test('ModuleManifest::fromArray exige version', function (): void {
    assert_throws(InstallerException::class, function (): void {
        ModuleManifest::fromArray(['clave' => 'x']);
    });
});
