<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudTransitionContext;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudTransitionService;
use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Exceptions\ValidationException;

require_once dirname(__DIR__, 1) . '/../fixtures/transition_guards.php';

function transition_machine(): CrudStateMachine
{
    return CrudStateMachine::fromArray([
        'column' => 'status',
        'values' => [
            'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
            'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
        ],
        'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
    ]);
}

function transition_ctx(string $from, string $to): CrudTransitionContext
{
    return new CrudTransitionContext(
        'eventos', 'dom_eventos', 'id', 9, '127.0.0.1',
        ['id' => 5, 'status' => $from], 'status', $from, $to, []
    );
}

test('CrudTransitionService::authorize allows a valid transition with no guard', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $svc->authorize(transition_machine(), null, transition_ctx('pendiente', 'autorizado'));
    // No exception => authorized.
    assert_true(true);
});

test('CrudTransitionService::authorize blocks an invalid transition', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    assert_throws(ValidationException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), null, transition_ctx('autorizado', 'pendiente'));
    });
});

test('CrudTransitionService::authorize runs the guard and passes the context', function (): void {
    RecordingTransitionGuard::$last = null;
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => RecordingTransitionGuard::class]));
    $svc->authorize(transition_machine(), 'g', transition_ctx('pendiente', 'autorizado'));
    assert_true(RecordingTransitionGuard::$last instanceof CrudTransitionContext, 'guard ran');
    assert_same('pendiente', RecordingTransitionGuard::$last->from());
    assert_same('autorizado', RecordingTransitionGuard::$last->to());
});

test('CrudTransitionService::authorize blocks when the guard throws', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => BlockingTransitionGuard::class]));
    assert_throws(\RuntimeException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), 'g', transition_ctx('pendiente', 'autorizado'));
    });
});

test('CrudTransitionService::authorize errors when guard key is missing from the registry', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    assert_throws(ValidationException::class, function () use ($svc): void {
        $svc->authorize(transition_machine(), 'ausente', transition_ctx('pendiente', 'autorizado'));
    });
});
