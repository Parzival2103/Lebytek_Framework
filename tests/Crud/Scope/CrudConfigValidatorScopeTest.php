<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: scope + scope_handler juntos es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => [
            'scope' => ['type' => 'owner', 'column' => 'created_by'],
            'scope_handler' => 'clientes_owner',
        ],
    ]);
    assert_true(in_array('list.scope y list.scope_handler son mutuamente excluyentes.', $errors, true), 'falta error de exclusión');
});

test('CrudConfigValidator: scope.type inválido es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'tenant', 'column' => 'created_by']],
    ]);
    assert_true(in_array("list.scope.type debe ser 'owner'.", $errors, true), 'falta error de type');
});

test('CrudConfigValidator: scope sin column es error', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'owner']],
    ]);
    assert_true(in_array('list.scope.column es obligatorio.', $errors, true), 'falta error de column');
});

test('CrudConfigValidator: scope owner válido no genera errores de forma', function (): void {
    $errors = CrudConfigValidator::scopeShapeErrors([
        'list' => ['scope' => ['type' => 'owner', 'column' => 'created_by', 'bypass_permission' => '{prefix}.ver_todos']],
    ]);
    assert_same([], $errors);
});

test('CrudConfigValidator: sin list.scope ni handler no genera errores', function (): void {
    assert_same([], CrudConfigValidator::scopeShapeErrors([]));
    assert_same([], CrudConfigValidator::scopeShapeErrors(['list' => ['columns' => []]]));
});
