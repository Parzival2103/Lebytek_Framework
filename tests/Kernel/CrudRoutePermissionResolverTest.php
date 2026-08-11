<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CrudRoutePermissionResolver;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Http\Request;

function crud_resolver(): CrudRoutePermissionResolver
{
    return new CrudRoutePermissionResolver(
        new CalendarConfigLoader(new CalendarConfigValidator())
    );
}

test('CrudRoutePermissionResolver maps GET index to {prefix}.ver', function (): void {
    if (!class_exists(CrudRoutePermissionResolver::class)) {
        throw new \RuntimeException(
            'CrudRoutePermissionResolver missing — add Application service per spec M3 F1'
        );
    }
    $request = new Request('GET', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps GET crear to {prefix}.crear', function (): void {
    $request = new Request('GET', '/admin/crud/demo_clientes/crear');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.crear', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps POST store to {prefix}.crear', function (): void {
    $request = new Request('POST', '/admin/crud/demo_clientes');
    $request->setRouteParams(['resource' => 'demo_clientes']);
    assert_same('demo_clientes.crear', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps POST eliminar to {prefix}.eliminar', function (): void {
    $request = new Request('POST', '/admin/crud/demo_clientes/42/eliminar');
    $request->setRouteParams(['resource' => 'demo_clientes', 'id' => '42']);
    assert_same('demo_clientes.eliminar', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps calendario index to linked CRUD {prefix}.ver', function (): void {
    $request = new Request('GET', '/admin/calendario/demo_citas');
    $request->setRouteParams(['key' => 'demo_citas']);
    assert_same('demo_citas.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver maps calendario eventos AJAX to {prefix}.ver', function (): void {
    $request = new Request('GET', '/admin/calendario/demo_citas/eventos');
    $request->setRouteParams(['key' => 'demo_citas']);
    assert_same('demo_citas.ver', crud_resolver()->resolve($request));
});

test('CrudRoutePermissionResolver throws ValidationException for unknown CRUD resource', function (): void {
    $request = new Request('GET', '/admin/crud/no_existe_xyz');
    $request->setRouteParams(['resource' => 'no_existe_xyz']);
    assert_throws(ValidationException::class, fn () => crud_resolver()->resolve($request));
});
