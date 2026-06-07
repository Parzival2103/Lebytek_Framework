<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Services\CrudActionService;
use App\Application\Services\CrudHandlerRegistry;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Exceptions\ValidationException;

require_once dirname(__DIR__, 1) . '/../fixtures/action_handlers.php';

function action_ctx(): CrudActionContext
{
    return new CrudActionContext('eventos', 'dom_eventos', 'id', 1, '127.0.0.1', 7, ['id' => 7, 'status' => 'pendiente'], 'autorizar', []);
}

test('CrudActionService::dispatch runs a handler action via the registry', function (): void {
    RecordingActionHandler::$last = null;
    $svc = new CrudActionService(
        new CrudHandlerRegistry(['evt_auth' => RecordingActionHandler::class])
    );
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'handler', 'handler' => 'evt_auth']);
    $svc->dispatch($action, action_ctx());
    assert_true(RecordingActionHandler::$last instanceof CrudActionContext, 'handler ran');
    assert_same('autorizar', RecordingActionHandler::$last->action());
});

test('CrudActionService::dispatch rejects link actions (navigation only)', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    $action = CrudActionDefinition::fromArray(['name' => 'pdf', 'type' => 'link', 'route' => '/x/{id}']);
    assert_throws(ValidationException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});

test('CrudActionService::dispatch rejects builtin and transition in Fase 1', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    foreach (['builtin', 'transition'] as $type) {
        $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => $type, 'handler' => 'h', 'to' => 't', 'route' => '/r']);
        assert_throws(ValidationException::class, function () use ($svc, $action): void {
            $svc->dispatch($action, action_ctx());
        });
    }
});

test('CrudActionService::dispatch rethrows a handler exception', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry(['fail' => FailingActionHandler::class]));
    $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'fail']);
    assert_throws(\RuntimeException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});

test('CrudActionService::dispatch errors when handler key is missing from the registry', function (): void {
    $svc = new CrudActionService(new CrudHandlerRegistry([]));
    $action = CrudActionDefinition::fromArray(['name' => 'x', 'type' => 'handler', 'handler' => 'ausente']);
    assert_throws(ValidationException::class, function () use ($svc, $action): void {
        $svc->dispatch($action, action_ctx());
    });
});
