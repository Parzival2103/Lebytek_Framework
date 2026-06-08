<?php

declare(strict_types=1);

use App\Application\Install\DependencyResolver;
use App\Application\Install\ModuleManifest;
use App\Domain\Exceptions\InstallerException;

function dr_manifest(string $clave, array $requiere = []): ModuleManifest
{
    return ModuleManifest::fromArray([
        'clave' => $clave, 'nombre' => $clave, 'version' => '1.0.0', 'requiere' => $requiere,
    ]);
}

test('DependencyResolver: ordena dependencias antes que dependientes e incluye core', function (): void {
    $manifests = [
        'core'  => dr_manifest('core'),
        'crud'  => dr_manifest('crud', ['core']),
        'inv'   => dr_manifest('inv', ['crud']),
    ];
    $orden = (new DependencyResolver())->resolver($manifests, ['inv']);
    assert_true(array_search('core', $orden) < array_search('crud', $orden));
    assert_true(array_search('crud', $orden) < array_search('inv', $orden));
});

test('DependencyResolver: core siempre presente aunque no se seleccione', function (): void {
    $manifests = ['core' => dr_manifest('core'), 'dash' => dr_manifest('dash', ['core'])];
    $orden = (new DependencyResolver())->resolver($manifests, ['dash']);
    assert_true(in_array('core', $orden, true));
});

test('DependencyResolver: ciclo lanza InstallerException', function (): void {
    $manifests = [
        'a' => dr_manifest('a', ['b']),
        'b' => dr_manifest('b', ['a']),
        'core' => dr_manifest('core'),
    ];
    assert_throws(InstallerException::class, function () use ($manifests): void {
        (new DependencyResolver())->resolver($manifests, ['a']);
    });
});
