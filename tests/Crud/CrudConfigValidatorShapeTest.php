<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('newBlockShapeErrors: empty config has no new-block errors', function (): void {
    assert_same([], CrudConfigValidator::newBlockShapeErrors([]));
});

test('newBlockShapeErrors: well-formed optional blocks pass', function (): void {
    $config = [
        'actions' => ['row' => [], 'bulk' => []],
        'states' => ['column' => 'status', 'values' => [], 'transitions' => []],
        'relations' => [],
        'detail' => ['tabs' => []],
        'form' => ['validators' => ['anticipo_minimo']],
    ];
    assert_same([], CrudConfigValidator::newBlockShapeErrors($config));
});

test('newBlockShapeErrors: wrong types are reported', function (): void {
    $errors = CrudConfigValidator::newBlockShapeErrors([
        'actions' => 'nope',
        'states' => 'nope',
        'relations' => 5,
        'detail' => 'nope',
        'form' => ['validators' => 'nope'],
    ]);
    assert_same(5, count($errors));
});

test('newBlockShapeErrors: states requires a column when present', function (): void {
    $errors = CrudConfigValidator::newBlockShapeErrors([
        'states' => ['values' => [], 'transitions' => []],
    ]);
    assert_same(['states.column es obligatorio cuando se define el bloque states.'], $errors);
});
