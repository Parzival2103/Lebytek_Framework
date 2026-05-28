<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudContext;

test('CrudActionContext: exposes action target', function (): void {
    $record = ['id' => 5, 'status' => 'pendiente'];
    $ctx = new CrudActionContext(
        'eventos',
        'dom_eventos',
        'id',
        3,
        '10.0.0.1',
        5,
        $record,
        'autorizar',
        ['nota' => 'ok']
    );
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(5, $ctx->recordId());
    assert_same($record, $ctx->record());
    assert_same('autorizar', $ctx->action());
    assert_same(['nota' => 'ok'], $ctx->input());
});

test('CrudActionContext: record may be null', function (): void {
    $ctx = new CrudActionContext('eventos', 'dom_eventos', 'id', 3, '', 5, null, 'x', []);
    assert_null($ctx->record());
});
