<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\CrudReporteDataSource;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\AccesoException;

function reporte_authz_def(string $prefix = 'demo_productos'): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_productos',
            'title' => 'Productos',
            'table' => 'dom_productos',
            'primary_key' => 'id',
            'permission_prefix' => $prefix,
        ],
        'list' => ['columns' => [['name' => 'id', 'label' => 'ID']]],
    ]);
}

test('assertCanViewResource permite cuando can otorga {prefix}.ver (C5)', function (): void {
    $def = reporte_authz_def();
    $can = static fn(string $slug): bool => $slug === 'demo_productos.ver';
    CrudReporteDataSource::assertCanViewResource($def, $can);
    assert_true(true, 'con .ver: no lanza');
});

test('assertCanViewResource deniega cuando can no otorga .ver (C5)', function (): void {
    $def = reporte_authz_def();
    $can = static fn(string $slug): bool => $slug === 'reportes.generar'; // típico vector
    assert_throws(AccesoException::class, function () use ($def, $can): void {
        CrudReporteDataSource::assertCanViewResource($def, $can);
    });
});

test('assertCanViewResource deniega cuando can es null (C5)', function (): void {
    $def = reporte_authz_def();
    assert_throws(AccesoException::class, function () use ($def): void {
        CrudReporteDataSource::assertCanViewResource($def, null);
    });
});

test('assertCanViewResource mensaje incluye el slug .ver', function (): void {
    $def = reporte_authz_def('demo_clientes');
    $msg = null;
    try {
        CrudReporteDataSource::assertCanViewResource($def, static fn(string $s): bool => false);
    } catch (AccesoException $e) {
        $msg = $e->getMessage();
    }
    assert_same('No tienes permiso para realizar esta acción: demo_clientes.ver', $msg);
});
