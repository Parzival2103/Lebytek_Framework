<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudContext;

test('CrudContext: exposes identity getters', function (): void {
    $ctx = new CrudContext('eventos', 'dom_eventos', 'id', 7, '127.0.0.1');
    assert_same('eventos', $ctx->resourceKey());
    assert_same('dom_eventos', $ctx->table());
    assert_same('id', $ctx->primaryKey());
    assert_same(7, $ctx->userId());
    assert_same('127.0.0.1', $ctx->ip());
});

test('CrudContext: userId may be null (anonymous/system)', function (): void {
    $ctx = new CrudContext('eventos', 'dom_eventos', 'id', null, '');
    assert_null($ctx->userId());
});
