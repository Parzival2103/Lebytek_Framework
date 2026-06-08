<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudStateMachine;

function demo_machine(): CrudStateMachine
{
    return CrudStateMachine::fromArray([
        'column' => 'status',
        'values' => [
            'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
            'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            'rechazado'  => ['label' => 'Rechazado',  'badge' => 'danger'],
        ],
        'transitions' => [
            'pendiente'  => ['autorizado', 'rechazado'],
            'autorizado' => [],
            'rechazado'  => [],
        ],
    ]);
}

test('CrudStateMachine: column is exposed', function (): void {
    assert_same('status', demo_machine()->column());
});

test('CrudStateMachine: valid transition is allowed', function (): void {
    assert_true(demo_machine()->canTransition('pendiente', 'autorizado'));
    assert_true(demo_machine()->canTransition('pendiente', 'rechazado'));
});

test('CrudStateMachine: invalid transition is rejected', function (): void {
    assert_true(!demo_machine()->canTransition('pendiente', 'pendiente'));
    assert_true(!demo_machine()->canTransition('autorizado', 'pendiente'));
    assert_true(!demo_machine()->canTransition('desconocido', 'autorizado'));
});

test('CrudStateMachine: terminal state has no outgoing transitions', function (): void {
    assert_same([], demo_machine()->allowedFrom('autorizado'));
    assert_same(['autorizado', 'rechazado'], demo_machine()->allowedFrom('pendiente'));
    assert_same([], demo_machine()->allowedFrom('inexistente'));
});

test('CrudStateMachine: label and badge resolve per state, null when unknown', function (): void {
    $m = demo_machine();
    assert_same('Pendiente', $m->label('pendiente'));
    assert_same('warning', $m->badge('pendiente'));
    assert_null($m->label('inexistente'));
    assert_null($m->badge('inexistente'));
    assert_true($m->isKnownState('rechazado'));
    assert_true(!$m->isKnownState('inexistente'));
});

test('CrudStateMachine: fromArray fills defaults for sparse value metadata', function (): void {
    $m = CrudStateMachine::fromArray([
        'column' => 'estado',
        'values' => ['nuevo' => []],
        'transitions' => [],
    ]);
    assert_same('nuevo', $m->label('nuevo'));        // label defaults to the key
    assert_same('secondary', $m->badge('nuevo'));     // badge defaults to secondary
});
