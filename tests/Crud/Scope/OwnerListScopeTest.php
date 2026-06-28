<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudListContext;
use Lebytek\Framework\Application\Crud\Scopes\OwnerListScope;

function scope_ctx(?int $userId): CrudListContext
{
    return new CrudListContext('clientes', 'dom_clientes', 'id', $userId, '127.0.0.1', []);
}

test('OwnerListScope: sin bypass añade created_by = userId', function (): void {
    $ctx = scope_ctx(7);
    (new OwnerListScope('created_by', false, 7))->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => 7]], $ctx->conditions());
});

test('OwnerListScope: con bypass no añade condición (ve todo)', function (): void {
    $ctx = scope_ctx(7);
    (new OwnerListScope('created_by', true, 7))->apply($ctx);
    assert_same([], $ctx->conditions());
});

test('OwnerListScope: userId null sin bypass aplica no-fuga (created_by = -1)', function (): void {
    $ctx = scope_ctx(null);
    (new OwnerListScope('created_by', false, null))->apply($ctx);
    assert_same([['column' => 'created_by', 'op' => '=', 'value' => -1]], $ctx->conditions());
});
