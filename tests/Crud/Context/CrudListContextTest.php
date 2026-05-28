<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudContext;
use App\Application\Crud\Context\CrudListContext;

function make_list_ctx(): CrudListContext
{
    return new CrudListContext('eventos', 'dom_eventos', 'id', 1, '', ['buscar' => 'x']);
}

test('CrudListContext: exposes query and starts with no conditions', function (): void {
    $ctx = make_list_ctx();
    assert_true($ctx instanceof CrudContext, 'must extend CrudContext');
    assert_same(['buscar' => 'x'], $ctx->query());
    assert_same([], $ctx->conditions());
});

test('CrudListContext: addCondition records normalized op', function (): void {
    $ctx = make_list_ctx();
    $ctx->addCondition('status', 'like', '%a%');
    $ctx->addCondition('monto', '>=', 100);
    assert_same(
        [
            ['column' => 'status', 'op' => 'LIKE', 'value' => '%a%'],
            ['column' => 'monto', 'op' => '>=', 'value' => 100],
        ],
        $ctx->conditions()
    );
});

test('CrudListContext: addCondition rejects ops outside the whitelist', function (): void {
    $ctx = make_list_ctx();
    assert_throws(\InvalidArgumentException::class, function () use ($ctx): void {
        $ctx->addCondition('status', 'OR 1=1', 'x');
    });
});
