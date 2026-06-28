<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Domain\Interfaces\CrudActionHandlerInterface;
use Lebytek\Framework\Domain\Interfaces\CrudHookHandlerInterface;

require_once dirname(__DIR__) . '/fixtures/hook_handlers.php';

test('CrudHandlerRegistry::resolve returns instance when interface matches', function (): void {
    $registry = new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]);
    $handler = $registry->resolve('mutator', CrudHookHandlerInterface::class);
    assert_true($handler instanceof CrudHookHandlerInterface);
});

test('CrudHandlerRegistry::resolve defaults to the hook interface', function (): void {
    $registry = new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]);
    $handler = $registry->resolve('mutator');
    assert_true($handler instanceof CrudHookHandlerInterface);
});

test('CrudHandlerRegistry::resolve returns null for unknown key', function (): void {
    $registry = new CrudHandlerRegistry([]);
    assert_null($registry->resolve('nope'));
    assert_null($registry->resolve(null));
});

test('CrudHandlerRegistry::resolve rejects a class that does not implement the expected interface', function (): void {
    $registry = new CrudHandlerRegistry(['act' => ActionOnlyHandler::class]);
    assert_throws(\RuntimeException::class, function () use ($registry): void {
        $registry->resolve('act', CrudHookHandlerInterface::class);
    });
});

test('CrudHandlerRegistry::resolve accepts the same class for a different expected interface', function (): void {
    $registry = new CrudHandlerRegistry(['act' => ActionOnlyHandler::class]);
    $handler = $registry->resolve('act', CrudActionHandlerInterface::class);
    assert_true($handler instanceof CrudActionHandlerInterface);
});
