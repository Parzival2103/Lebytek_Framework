<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudWriteContext;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudHookRunner;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

require_once dirname(__DIR__) . '/fixtures/hook_handlers.php';

function runner_definition(?string $handlerKey): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => ['key' => 'clientes', 'table' => 'dom_clientes', 'primary_key' => 'id'],
        'hooks' => $handlerKey !== null ? ['handler' => $handlerKey] : [],
    ]);
}

function runner_write_ctx(array $data): CrudWriteContext
{
    return new CrudWriteContext('clientes', 'dom_clientes', 'id', 1, '', [], null, null, $data, true);
}

test('CrudHookRunner: handler mutation is visible after run (read-back mechanism)', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['mutator' => MutatingHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('mutator'), 'beforeCreate', $ctx);
    assert_same('from-hook', $ctx->data()['slug']);
});

test('CrudHookRunner: no handler configured is a safe no-op', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry([]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition(null), 'beforeCreate', $ctx);
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudHookRunner: unknown handler key is a safe no-op', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry([]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('ghost'), 'beforeCreate', $ctx);
    assert_same(['nombre' => 'Ana'], $ctx->data());
});

test('CrudHookRunner: an exception in a hook aborts (rethrows)', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['boom' => ThrowingHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    assert_throws(\RuntimeException::class, function () use ($runner, $ctx): void {
        $runner->run(runner_definition('boom'), 'beforeUpdate', $ctx);
    });
});

test('CrudHookRunner: legacy beforeStore alias fires on beforeCreate when defined', function (): void {
    $runner = new CrudHookRunner(new CrudHandlerRegistry(['legacy' => LegacyAliasHookHandler::class]));
    $ctx = runner_write_ctx(['nombre' => 'Ana']);
    $runner->run(runner_definition('legacy'), 'beforeCreate', $ctx);
    assert_same('yes', $ctx->data()['legacy']);
});
