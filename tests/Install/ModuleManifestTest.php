<?php

declare(strict_types=1);

use App\Application\Install\ModuleManifest;
use App\Domain\Exceptions\InstallerException;

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
