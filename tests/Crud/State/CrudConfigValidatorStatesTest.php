<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('statesBlockErrors: no states block is fine', function (): void {
    assert_same([], CrudConfigValidator::statesBlockErrors([]));
});

test('statesBlockErrors: well-formed states + transitions + transition action pass', function (): void {
    $config = [
        'states' => [
            'column' => 'status',
            'values' => [
                'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
                'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            ],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
        'actions' => ['row' => [
            ['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado'],
        ]],
    ];
    assert_same([], CrudConfigValidator::statesBlockErrors($config));
});

test('statesBlockErrors: non-array states reported', function (): void {
    assert_same(['states debe ser un objeto.'], CrudConfigValidator::statesBlockErrors(['states' => 'nope']));
});

test('statesBlockErrors: values must be a non-empty object', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors(['states' => ['column' => 'status', 'values' => []]]);
    assert_same(1, count($errors));
});

test('statesBlockErrors: unknown transition source and target are reported', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors(['states' => [
        'column' => 'status',
        'values' => ['a' => [], 'b' => []],
        'transitions' => [
            'a' => ['b', 'zzz'],   // zzz unknown target
            'ghost' => ['a'],      // ghost unknown source
        ],
    ]]);
    assert_same(2, count($errors));
});

test('statesBlockErrors: transition action pointing to an unknown state is reported', function (): void {
    $errors = CrudConfigValidator::statesBlockErrors([
        'states' => ['column' => 'status', 'values' => ['a' => []], 'transitions' => ['a' => []]],
        'actions' => ['row' => [
            ['name' => 'go', 'type' => 'transition', 'to' => 'nowhere'],
        ]],
    ]);
    assert_same(1, count($errors));
});
