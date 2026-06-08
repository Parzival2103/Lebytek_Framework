<?php

declare(strict_types=1);

use App\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: relation con type inválido es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'manyToMany', 'table' => 'dom_x', 'foreign_key' => 'x_id']],
    ]);
    assert_true(in_array("relations.x.type debe ser 'belongsTo' o 'hasMany'.", $errors, true), 'falta error de type');
});

test('CrudConfigValidator: relation con tabla de prefijo bloqueado es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'belongsTo', 'table' => 'auth_usuarios', 'foreign_key' => 'x_id', 'value' => 'id', 'label' => 'nombre']],
    ]);
    assert_true(in_array("relations.x.table (auth_usuarios) usa un prefijo bloqueado.", $errors, true), 'falta error de prefijo');
});

test('CrudConfigValidator: detail tab relation debe referenciar una relación existente', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'relations' => [],
        'detail' => ['tabs' => [['key' => 'items', 'type' => 'relation', 'relation' => 'items']]],
    ]);
    assert_true(in_array("detail.tabs[0] (relation) referencia una relación inexistente: 'items'.", $errors, true), 'falta error de relación');
});

test('CrudConfigValidator: detail tab component con .. (traversal) es error', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'detail' => ['tabs' => [['key' => 'c', 'type' => 'component', 'view' => '../../etc/passwd']]],
    ]);
    assert_true(in_array("detail.tabs[0] (component) tiene una vista con ruta inválida.", $errors, true), 'falta error de traversal');
});

test('CrudConfigValidator: relations/detail vacíos no generan errores', function (): void {
    assert_same([], CrudConfigValidator::relationsBlockErrors([]));
    assert_same([], CrudConfigValidator::detailBlockErrors([]));
});
