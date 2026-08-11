<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Crud\Context\CrudTransitionContext;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudTransitionService;
use Lebytek\Framework\Domain\Entities\Crud\CrudActionDefinition;
use Lebytek\Framework\Domain\Entities\Crud\CrudStateMachine;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

require_once dirname(__DIR__, 1) . '/../fixtures/transition_guards.php';
require_once dirname(__DIR__, 1) . '/../fixtures/fake_crud_repository.php';

final class RecordingBitacora implements BitacoraRepositoryInterface
{
    public int $calls = 0;
    public string $lastDetalle = '';

    public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void
    {
        $this->calls++;
        $this->lastDetalle = $detalle;
    }

    public function recientes(int $limit = 50): array
    {
        return [];
    }

    public function porRegistro(string $tabla, int $registroId, int $limit = 50): array
    {
        return [];
    }
}

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

function eventos_definition_with_states(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos', 'title' => 'Eventos', 'table' => 'dom_eventos',
            'primary_key' => 'id', 'permission_prefix' => 'eventos',
        ],
        'states' => [
            'column' => 'status',
            'values' => ['pendiente' => [], 'autorizado' => []],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
    ]);
}

test('CrudTransitionService::apply throws when the resource has no state machine', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $def = CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'x', 'title' => 'X', 'table' => 'dom_x',
            'primary_key' => 'id', 'permission_prefix' => 'x',
        ],
    ]);
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    assert_throws(ValidationException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    });
});

test('CrudTransitionService::apply blocks an invalid transition before persisting', function (): void {
    // repository is null: if apply() tried to persist it would fatal; it must throw first.
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]));
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'reabrir', 'type' => 'transition', 'to' => 'pendiente']);
    assert_throws(ValidationException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'autorizado'], 7, '127.0.0.1');
    });
});

test('CrudTransitionService::apply runs the guard and blocks before persisting', function (): void {
    $svc = new CrudTransitionService(new CrudHandlerRegistry(['g' => BlockingTransitionGuard::class]));
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado', 'guard' => 'g']);
    assert_throws(\RuntimeException::class, function () use ($svc, $def, $action): void {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    });
});

test('apply CAS succeeds when DB status matches from', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'pendiente', 'deleted' => 0];
    $bit = new RecordingBitacora();
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, $bit);
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    assert_same('autorizado', $repo->rowsById[1]['status']);
    assert_same(1, $bit->calls);
    assert_same(['deleted' => 0, 'status' => 'pendiente'], $repo->updateCalls[0]['expected']);
});

test('apply CAS conflicts after retry when status already changed', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'autorizado', 'deleted' => 0];
    $bit = new RecordingBitacora();
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, $bit);
    $def = eventos_definition_with_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $caught = null;
    try {
        $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    } catch (ValidationException $e) {
        $caught = $e;
    }
    assert_true($caught instanceof ValidationException);
    assert_same('El registro cambió; recarga e inténtalo de nuevo.', $caught->getMessage());
    assert_same(0, $bit->calls, 'no bitácora on conflict');
    assert_same('autorizado', $repo->rowsById[1]['status']);
});

function eventos_definition_three_states(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'eventos', 'title' => 'Eventos', 'table' => 'dom_eventos',
            'primary_key' => 'id', 'permission_prefix' => 'eventos',
        ],
        'states' => [
            'column' => 'status',
            'values' => ['pendiente' => [], 'intermedio' => [], 'autorizado' => []],
            'transitions' => [
                'pendiente' => ['intermedio', 'autorizado'],
                'intermedio' => ['autorizado'],
                'autorizado' => [],
            ],
        ],
    ]);
}

test('apply CAS retries once and logs actual from state on success', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'intermedio', 'deleted' => 0];
    $bit = new RecordingBitacora();
    $svc = new CrudTransitionService(new CrudHandlerRegistry([]), $repo, $bit);
    $def = eventos_definition_three_states();
    $action = CrudActionDefinition::fromArray(['name' => 'autorizar', 'type' => 'transition', 'to' => 'autorizado']);
    $svc->apply($def, $action, ['id' => 1, 'status' => 'pendiente'], 7, '127.0.0.1');
    assert_same('autorizado', $repo->rowsById[1]['status']);
    assert_same(1, $bit->calls);
    assert_same(2, count($repo->updateCalls), 'first CAS fails, retry succeeds');
    assert_same(['deleted' => 0, 'status' => 'pendiente'], $repo->updateCalls[0]['expected']);
    assert_same(['deleted' => 0, 'status' => 'intermedio'], $repo->updateCalls[1]['expected']);
    $detalle = json_decode($bit->lastDetalle, true);
    assert_true(is_array($detalle));
    assert_same('intermedio', $detalle['from'] ?? null, 'bitácora logs actual DB from on retry success');
    assert_same('autorizado', $detalle['to'] ?? null);
});
