<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: exists sin table es error de forma', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'cliente_id', 'validation' => ['exists' => ['column' => 'id']]],
        ]],
    ]);
    assert_true(in_array("form.fields[0].validation.exists.table es obligatorio.", $errors, true), 'falta error de exists.table');
});

test('CrudConfigValidator: exists con table de prefijo bloqueado es error', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'cliente_id', 'validation' => ['exists' => ['table' => 'auth_usuarios', 'column' => 'id']]],
        ]],
    ]);
    assert_true(
        in_array("form.fields[0].validation.exists.table (auth_usuarios) usa un prefijo bloqueado.", $errors, true),
        'falta error de prefijo bloqueado'
    );
});

test('CrudConfigValidator: unique mal formado es error', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [
            ['name' => 'folio', 'validation' => ['unique' => 'si']],
        ]],
    ]);
    assert_true(in_array("form.fields[0].validation.unique debe ser true u objeto {ignore_self:true}.", $errors, true), 'falta error de unique');
});

test('CrudConfigValidator: config sin constraints no genera errores de forma', function (): void {
    $errors = CrudConfigValidator::validationConstraintShapeErrors([
        'form' => ['fields' => [['name' => 'nombre', 'validation' => ['maxlength' => 60]]]],
    ]);
    assert_same([], $errors);
});
