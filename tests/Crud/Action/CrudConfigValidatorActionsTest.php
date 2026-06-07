<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('actionsBlockErrors: no actions block is fine', function (): void {
    assert_same([], CrudConfigValidator::actionsBlockErrors([]));
});

test('actionsBlockErrors: well-formed actions pass', function (): void {
    $config = ['actions' => [
        'row' => [
            ['name' => 'edit', 'type' => 'builtin'],
            ['name' => 'toggle', 'type' => 'handler', 'handler' => 'p_toggle'],
            ['name' => 'pdf', 'type' => 'link', 'route' => '/admin/x/{id}/pdf'],
        ],
        'bulk' => [
            ['name' => 'activar', 'type' => 'handler', 'handler' => 'p_bulk'],
        ],
    ]];
    assert_same([], CrudConfigValidator::actionsBlockErrors($config));
});

test('actionsBlockErrors: reports structural problems', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => [
        'row' => [
            ['type' => 'handler', 'handler' => 'h'],            // missing name
            ['name' => 'bad', 'type' => 'nope'],                // bad type
            ['name' => 'h2', 'type' => 'handler'],              // handler missing handler key
            ['name' => 'l2', 'type' => 'link'],                 // link missing route
            ['name' => 'b2', 'type' => 'builtin'],              // builtin not in show/edit/delete
            ['name' => 'm2', 'type' => 'handler', 'handler' => 'h', 'method' => 'PUT'], // bad method
        ],
    ]]);
    assert_same(6, count($errors));
});

test('actionsBlockErrors: actions/row/bulk must be arrays', function (): void {
    $errors = CrudConfigValidator::actionsBlockErrors(['actions' => 'nope']);
    assert_same(['actions debe ser un objeto.'], $errors);

    $errors2 = CrudConfigValidator::actionsBlockErrors(['actions' => ['row' => 'x', 'bulk' => 5]]);
    assert_same(2, count($errors2));
});
