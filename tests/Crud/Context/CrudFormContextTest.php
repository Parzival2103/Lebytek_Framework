<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudContext;
use Lebytek\Framework\Application\Crud\Context\CrudFormContext;

test('CrudFormContext: exposes edit flag and record', function (): void {
    $ctx = new CrudFormContext('eventos', 'dom_eventos', 'id', 1, '', true, ['id' => 5]);
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_true($ctx->isEdit());
    assert_same(['id' => 5], $ctx->record());
    assert_same([], $ctx->fieldOptions());
    assert_same([], $ctx->fieldValues());
});

test('CrudFormContext: collects option and value overrides', function (): void {
    $ctx = new CrudFormContext('eventos', 'dom_eventos', 'id', 1, '', false, null);
    $ctx->setFieldOptions('categoria_id', [['value' => 1, 'label' => 'A']]);
    $ctx->setFieldValue('codigo', 'EV-001');
    assert_same(['categoria_id' => [['value' => 1, 'label' => 'A']]], $ctx->fieldOptions());
    assert_same(['codigo' => 'EV-001'], $ctx->fieldValues());
});
