<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('actionsBlockErrors: no actions block is fine', function (): void {
    assert_same([], CrudConfigValidator::actionsBlockErrors([]));
});

test('actionsBlockErrors: well-formed actions pass', function (): void {
    $config = ['actions' => [
        'row' => [
            ['name' => 'edit', 'type' => 'builtin'],
            ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle', 'permission' => 'editar'],
            ['name' => 'pdf', 'type' => 'link', 'route' => '/admin/x/{id}/pdf'],
        ],
        'bulk' => [
            ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk', 'permission' => 'editar'],
        ],
    ]];
    assert_same([], CrudConfigValidator::actionsBlockErrors($config));
});

test('actionsBlockErrors: reports structural problems', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['type' => 'handler', 'handler' => 'h', 'permission' => 'editar'],            // missing name
            ['name' => 'bad', 'type' => 'nope'],                // bad type
            ['name' => 'h2', 'type' => 'handler'],              // handler missing handler key
            ['name' => 'l2', 'type' => 'link'],                 // link missing route
            ['name' => 'b2', 'type' => 'builtin'],              // builtin not in show/edit/delete
            ['name' => 'm2', 'type' => 'handler', 'handler' => 'h', 'method' => 'PUT', 'permission' => 'editar'], // bad method
        ],
    ]]);
    assert_same(7, count($errors));
});

test('actionsBlockErrors: handler sin permission es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle'],
        ],
    ]]);
    assert_true(
        in_array("actions.row[0] (handler) requiere 'permission'.", $errors, true),
        'handler sin permission debe rechazarse'
    );
});

test('actionsBlockErrors: transition sin permission es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'pagar', 'type' => 'transition', 'to' => 'pagado'],
        ],
    ]]);
    assert_true(
        in_array("actions.row[0] (transition) requiere 'permission'.", $errors, true),
        'transition sin permission debe rechazarse'
    );
});

test('actionsBlockErrors: link y builtin sin permission siguen válidos', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['name' => 'show', 'type' => 'builtin'],
            ['name' => 'pdf', 'type' => 'link', 'route' => '/admin/x/{id}/pdf'],
        ],
    ]]);
    assert_same([], $errors);
});

test('actionsBlockErrors: handler con permission vacía es error (C2)', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'bulk' => [
            ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk', 'permission' => ''],
        ],
    ]]);
    assert_true(
        in_array("actions.bulk[0] (handler) requiere 'permission'.", $errors, true),
        'permission vacía debe rechazarse'
    );
});

test('actionsBlockErrors: actions/row/bulk must be arrays', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => 'nope']);
    assert_same(['actions debe ser un objeto.'], $errors);

    $errors2 = CrudConfigValidator::actionsBlockErrors(['actions' => ['row' => 'x', 'bulk' => 5]]);
    assert_same(2, count($errors2));
});
