<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\Crud\CrudStateMachine;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

function states_config(array $extra = []): array
{
    return array_merge([
        'resource' => [
            'key' => 'eventos', 'title' => 'Eventos', 'table' => 'dom_eventos',
            'primary_key' => 'id', 'permission_prefix' => 'eventos',
        ],
    ], $extra);
}

test('CrudResourceDefinition: no states block => stateMachine is null', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config());
    assert_true(!$def->hasStates());
    assert_null($def->stateMachine());
});

test('CrudResourceDefinition: states block is parsed into a CrudStateMachine', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config([
        'states' => [
            'column' => 'status',
            'values' => [
                'pendiente'  => ['label' => 'Pendiente',  'badge' => 'warning'],
                'autorizado' => ['label' => 'Autorizado', 'badge' => 'success'],
            ],
            'transitions' => ['pendiente' => ['autorizado'], 'autorizado' => []],
        ],
    ]));
    assert_true($def->hasStates());
    $machine = $def->stateMachine();
    assert_true($machine instanceof CrudStateMachine);
    assert_same('status', $machine->column());
    assert_true($machine->canTransition('pendiente', 'autorizado'));
    assert_same('success', $machine->badge('autorizado'));
});

test('CrudResourceDefinition: non-array states block is ignored', function (): void {
    $def = CrudResourceDefinition::fromArray(states_config(['states' => 'nope']));
    assert_true(!$def->hasStates());
    assert_null($def->stateMachine());
});
