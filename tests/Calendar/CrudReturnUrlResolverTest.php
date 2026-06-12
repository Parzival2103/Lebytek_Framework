<?php

declare(strict_types=1);

use App\Application\Services\CalendarConfigLoader;
use App\Application\Services\CalendarConfigValidator;
use App\Application\Services\CrudReturnUrlResolver;

test('CrudReturnUrlResolver redirige a calendario cuando el recurso está vinculado', function (): void {
    $resolver = new CrudReturnUrlResolver(new CalendarConfigLoader(new CalendarConfigValidator()));

    assert_same(
        '/admin/calendario/demo_citas',
        $resolver->resolve('demo_citas', null),
        'recurso con calendario vinculado'
    );
});

test('CrudReturnUrlResolver respeta return_to válido del calendario', function (): void {
    $resolver = new CrudReturnUrlResolver(new CalendarConfigLoader(new CalendarConfigValidator()));

    assert_same(
        '/admin/calendario/demo_citas',
        $resolver->resolve('demo_citas', '/admin/calendario/demo_citas'),
        'return_to explícito'
    );
});

test('CrudReturnUrlResolver ignora return_to inválido y cae al calendario vinculado', function (): void {
    $resolver = new CrudReturnUrlResolver(new CalendarConfigLoader(new CalendarConfigValidator()));

    assert_same(
        '/admin/calendario/demo_citas',
        $resolver->resolve('demo_citas', '/admin/calendario/otro_recurso'),
        'return_to de otro calendario'
    );
});

test('CrudReturnUrlResolver usa CRUD cuando el recurso no tiene calendario', function (): void {
    $resolver = new CrudReturnUrlResolver(new CalendarConfigLoader(new CalendarConfigValidator()));

    assert_same(
        '/admin/crud/demo_productos',
        $resolver->resolve('demo_productos', null),
        'recurso sin calendario'
    );
});
