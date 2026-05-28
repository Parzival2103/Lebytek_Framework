<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudValidationContext;

function make_validation_ctx(bool $isEdit = false): CrudValidationContext
{
    return new CrudValidationContext(
        'eventos',
        'dom_eventos',
        'id',
        1,
        '',
        ['monto' => '100'],          // input
        ['monto' => 100],            // normalized
        $isEdit ? ['id' => 5] : null, // record
        $isEdit
    );
}

test('CrudValidationContext: starts with no errors', function (): void {
    $ctx = make_validation_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_true(!$ctx->hasErrors(), 'should start clean');
    assert_same([], $ctx->errors());
    assert_same(['monto' => '100'], $ctx->input());
    assert_same(['monto' => 100], $ctx->normalized());
    assert_null($ctx->record());
    assert_true(!$ctx->isEdit());
});

test('CrudValidationContext: addError accumulates per field', function (): void {
    $ctx = make_validation_ctx();
    $ctx->addError('monto', 'Muy bajo');
    $ctx->addError('monto', 'Debe ser positivo');
    $ctx->addError('fecha', 'Requerida');
    assert_true($ctx->hasErrors());
    assert_same(
        ['monto' => ['Muy bajo', 'Debe ser positivo'], 'fecha' => ['Requerida']],
        $ctx->errors()
    );
});
