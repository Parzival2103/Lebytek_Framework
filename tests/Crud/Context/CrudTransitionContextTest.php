<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudTransitionContext;

test('CrudTransitionContext: exposes from/to and status column', function (): void {
    $record = ['id' => 1, 'status' => 'pendiente'];
    $ctx = new CrudTransitionContext(
        'eventos',
        'dom_eventos',
        'id',
        9,
        '::1',
        $record,
        'status',
        'pendiente',
        'autorizado',
        ['motivo' => 'aprobado']
    );
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same($record, $ctx->record());
    assert_same('status', $ctx->statusColumn());
    assert_same('pendiente', $ctx->from());
    assert_same('autorizado', $ctx->to());
    assert_same(['motivo' => 'aprobado'], $ctx->input());
});
