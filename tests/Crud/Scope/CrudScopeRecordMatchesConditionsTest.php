<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudScopeResolver;

/**
 * Paridad entre la evaluación en memoria (acceso por ID) y lo que SQL haría
 * con las mismas condiciones. Cualquier divergencia es un fail-open de IDOR.
 */

function rmc(array $record, array $conditions): bool
{
    return CrudScopeResolver::recordMatchesConditions($record, $conditions);
}

test('recordMatchesConditions: sin condiciones permite', function (): void {
    assert_true(rmc(['id' => 1], []));
});

test('recordMatchesConditions: columna ausente deniega', function (): void {
    assert_false(rmc(['id' => 1], [['column' => 'created_by', 'op' => '=', 'value' => 1]]));
});

test('recordMatchesConditions: = con valor de registro null deniega (SQL no matchea NULL)', function (): void {
    assert_false(rmc(['created_by' => null], [['column' => 'created_by', 'op' => '=', 'value' => null]]));
    assert_false(rmc(['created_by' => null], [['column' => 'created_by', 'op' => '=', 'value' => 7]]));
    assert_false(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '=', 'value' => null]]));
});

test('recordMatchesConditions: != con cualquier lado null deniega', function (): void {
    assert_false(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '!=', 'value' => null]]));
    assert_false(rmc(['created_by' => null], [['column' => 'created_by', 'op' => '!=', 'value' => 7]]));
    assert_false(rmc(['created_by' => null], [['column' => 'created_by', 'op' => '!=', 'value' => null]]));
});

test('recordMatchesConditions: = / != con ambos lados no nulos conserva comparación por string', function (): void {
    assert_true(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '=', 'value' => '7']]));
    assert_false(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '=', 'value' => 8]]));
    assert_true(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '!=', 'value' => 8]]));
    assert_false(rmc(['created_by' => 7], [['column' => 'created_by', 'op' => '!=', 'value' => '7']]));
});

test('recordMatchesConditions: > y < comparan fechas como texto, no como float', function (): void {
    $rec = ['fecha' => '2026-08-07'];
    assert_true(rmc($rec, [['column' => 'fecha', 'op' => '>', 'value' => '2026-01-01']]));
    assert_false(rmc($rec, [['column' => 'fecha', 'op' => '<', 'value' => '2026-01-01']]));
    assert_true(rmc($rec, [['column' => 'fecha', 'op' => '>=', 'value' => '2026-08-07']]));
    assert_true(rmc($rec, [['column' => 'fecha', 'op' => '<=', 'value' => '2026-08-07']]));
});

test('recordMatchesConditions: > y < siguen comparando números como números', function (): void {
    assert_true(rmc(['total' => '10'], [['column' => 'total', 'op' => '>', 'value' => '9']]));
    assert_false(rmc(['total' => '10'], [['column' => 'total', 'op' => '<', 'value' => '9']]));
    assert_true(rmc(['total' => 2.5], [['column' => 'total', 'op' => '>=', 'value' => 2.5]]));
});

test('recordMatchesConditions: orden con null deniega en todos los operadores', function (): void {
    foreach (['<', '>', '<=', '>='] as $op) {
        assert_false(rmc(['fecha' => null], [['column' => 'fecha', 'op' => $op, 'value' => '2026-01-01']]), "null actual con {$op}");
        assert_false(rmc(['fecha' => '2026-01-01'], [['column' => 'fecha', 'op' => $op, 'value' => null]]), "null esperado con {$op}");
    }
});

test('recordMatchesConditions: LIKE es case-insensitive como una colación ci', function (): void {
    assert_true(rmc(['nombre' => 'ACME S.A.'], [['column' => 'nombre', 'op' => 'LIKE', 'value' => '%acme%']]));
    assert_true(rmc(['nombre' => 'acme'], [['column' => 'nombre', 'op' => 'LIKE', 'value' => 'ACME']]));
    assert_true(rmc(['nombre' => 'Ácido'], [['column' => 'nombre', 'op' => 'LIKE', 'value' => 'ácido']]));
    assert_false(rmc(['nombre' => 'otro'], [['column' => 'nombre', 'op' => 'LIKE', 'value' => '%acme%']]));
});

test('recordMatchesConditions: LIKE con valor null deniega', function (): void {
    assert_false(rmc(['nombre' => null], [['column' => 'nombre', 'op' => 'LIKE', 'value' => '%acme%']]));
});

test('recordMatchesConditions: LIKE respeta comodines _ y %', function (): void {
    assert_true(rmc(['codigo' => 'AB1'], [['column' => 'codigo', 'op' => 'LIKE', 'value' => 'AB_']]));
    assert_false(rmc(['codigo' => 'AB12'], [['column' => 'codigo', 'op' => 'LIKE', 'value' => 'AB_']]));
    assert_true(rmc(['codigo' => 'AB12'], [['column' => 'codigo', 'op' => 'LIKE', 'value' => 'AB%']]));
});

test('recordMatchesConditions: IN conserva su comportamiento y deniega con null', function (): void {
    assert_true(rmc(['created_by' => 2], [['column' => 'created_by', 'op' => 'IN', 'value' => [1, 2, 3]]]));
    assert_false(rmc(['created_by' => 9], [['column' => 'created_by', 'op' => 'IN', 'value' => [1, 2, 3]]]));
    assert_false(rmc(['created_by' => 2], [['column' => 'created_by', 'op' => 'IN', 'value' => []]]));
    assert_false(rmc(['created_by' => null], [['column' => 'created_by', 'op' => 'IN', 'value' => [1, 2, 3]]]));
});

test('recordMatchesConditions: operador desconocido deniega', function (): void {
    assert_false(rmc(['created_by' => 1], [['column' => 'created_by', 'op' => 'REGEXP', 'value' => '.*']]));
});

test('recordMatchesConditions: valores no escalares denegan en vez de romper el cast', function (): void {
    assert_false(rmc(['meta' => ['a' => 1]], [['column' => 'meta', 'op' => '=', 'value' => 'a']]));
    assert_false(rmc(['meta' => 'a'], [['column' => 'meta', 'op' => '>', 'value' => ['a']]]));
});

test('recordMatchesConditions: todas las condiciones deben cumplirse (AND)', function (): void {
    $rec = ['created_by' => 7, 'estado' => 'activo'];
    assert_true(rmc($rec, [
        ['column' => 'created_by', 'op' => '=', 'value' => 7],
        ['column' => 'estado', 'op' => '=', 'value' => 'activo'],
    ]));
    assert_false(rmc($rec, [
        ['column' => 'created_by', 'op' => '=', 'value' => 7],
        ['column' => 'estado', 'op' => '=', 'value' => 'inactivo'],
    ]));
});
