<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudTableBuilder;

test('CrudTableBuilder: truncate format limits displayed length', function (): void {
    $builder = new CrudTableBuilder();
    $columns = [[
        'name' => 'api_tenant_public_id',
        'label' => 'Tenant API',
        'format' => 'truncate',
        'max_length' => 26,
        'badge' => [],
    ]];
    $long = '01JABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $row = ['api_tenant_public_id' => $long];
    $ref = new ReflectionClass($builder);
    $method = $ref->getMethod('formatRow');
    $method->setAccessible(true);
    $out = $method->invoke($builder, $row, $columns);
    assert_same('01JABCDEFGHIJKLMNOPQRSTUV…', $out['_formatted']['api_tenant_public_id']);
});

test('CrudTableBuilder: badge_nonempty applies badge when value present', function (): void {
    $builder = new CrudTableBuilder();
    $columns = [[
        'name' => 'api_provision_error',
        'label' => 'Error API',
        'format' => '',
        'badge' => [],
        'badge_nonempty' => 'danger',
    ]];
    $ref = new ReflectionClass($builder);
    $method = $ref->getMethod('formatRow');
    $method->setAccessible(true);
    $out = $method->invoke($builder, ['api_provision_error' => 'timeout'], $columns);
    assert_same('danger', $out['_badge']['api_provision_error']);
});
