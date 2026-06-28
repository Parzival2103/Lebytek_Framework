<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudContext;
use Lebytek\Framework\Application\Crud\Context\CrudWriteContext;

function make_write_ctx(array $data = ['nombre' => 'Ana'], bool $isCreate = true): CrudWriteContext
{
    return new CrudWriteContext(
        'clientes',
        'dom_clientes',
        'id',
        42,
        '127.0.0.1',
        ['nombre' => 'Ana'],   // input
        null,                  // record
        null,                  // recordId
        $data,                 // data
        $isCreate              // isCreate
    );
}

test('CrudWriteContext: is a CrudContext and exposes write state', function (): void {
    $ctx = make_write_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(['nombre' => 'Ana'], $ctx->input());
    assert_null($ctx->record());
    assert_null($ctx->recordId());
    assert_true($ctx->isCreate());
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudWriteContext: setData replaces the whole payload', function (): void {
    $ctx = make_write_ctx();
    $ctx->setData(['nombre' => 'Ana', 'slug' => 'ana']);
    assert_same(['nombre' => 'Ana', 'slug' => 'ana'], $ctx->data());
});

test('CrudWriteContext: mergeData patches keys', function (): void {
    $ctx = make_write_ctx();
    $ctx->mergeData(['slug' => 'ana', 'nombre' => 'Ana M.']);
    assert_same(['nombre' => 'Ana M.', 'slug' => 'ana'], $ctx->data());
});

test('CrudWriteContext: set writes a single key', function (): void {
    $ctx = make_write_ctx();
    $ctx->set('slug', 'ana');
    assert_same('ana', $ctx->data()['slug']);
});

test('CrudWriteContext: setRecordId is used after insert', function (): void {
    $ctx = make_write_ctx();
    $ctx->setRecordId(99);
    assert_same(99, $ctx->recordId());
});
