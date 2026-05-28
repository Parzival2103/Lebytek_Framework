<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudWriteContext;
use App\Application\Crud\Handlers\AbstractCrudHookHandler;
use App\Domain\Interfaces\CrudHookHandlerInterface;

test('AbstractCrudHookHandler: is a CrudHookHandlerInterface with no-op typed hooks', function (): void {
    $handler = new class extends AbstractCrudHookHandler {};
    assert_true($handler instanceof CrudHookHandlerInterface);

    $ctx = new CrudWriteContext('r', 't', 'id', 1, '', [], null, null, ['a' => 1], true);
    // No-ops must not throw and must not mutate data.
    $handler->beforeCreate($ctx);
    $handler->afterCreate($ctx);
    $handler->beforeUpdate($ctx);
    $handler->afterUpdate($ctx);
    $handler->beforeDelete($ctx);
    $handler->afterDelete($ctx);
    assert_same(['a' => 1], $ctx->data());
});

test('AbstractCrudHookHandler: subclass can override beforeCreate to mutate data', function (): void {
    $handler = new class extends AbstractCrudHookHandler {
        public function beforeCreate(CrudWriteContext $ctx): void
        {
            $ctx->set('slug', 'generated');
        }
    };
    $ctx = new CrudWriteContext('r', 't', 'id', 1, '', [], null, null, ['nombre' => 'X'], true);
    $handler->beforeCreate($ctx);
    assert_same('generated', $ctx->data()['slug']);
});
