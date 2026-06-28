<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDbConstraintValidator;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

require_once dirname(__DIR__, 2) . '/fixtures/constraint_repos.php';

function constraint_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_pedidos', 'title' => 'Pedidos', 'table' => 'dom_demo_pedidos',
            'primary_key' => 'id', 'permission_prefix' => 'demo_pedidos',
        ],
        'form' => ['fields' => [
            ['name' => 'folio', 'label' => 'Folio', 'type' => 'text',
             'validation' => ['unique' => ['ignore_self' => true], 'messages' => ['unique' => 'Folio repetido']]],
            ['name' => 'cliente_id', 'label' => 'Cliente', 'type' => 'relation', 'relation' => 'cliente',
             'validation' => ['exists' => ['table' => 'dom_demo_clientes', 'column' => 'id']]],
        ]],
    ]);
}

test('CrudDbConstraintValidator: unique conflict adds custom message', function (): void {
    $repo = new FakeConstraintRepository();
    $repo->unique['dom_demo_pedidos.folio.P-1.7'] = true; // existe otra fila con ese folio
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'P-1', 'cliente_id' => 3], 7);
    assert_same(['Folio repetido'], $errors['folio'] ?? []);
});

test('CrudDbConstraintValidator: unique passes when no conflict', function (): void {
    $repo = new FakeConstraintRepository();
    $repo->reference['dom_demo_clientes.id.3'] = true; // cliente referenciado existe
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'P-1', 'cliente_id' => 3], null);
    assert_same([], $errors);
});

test('CrudDbConstraintValidator: exists failure adds default message', function (): void {
    $repo = new FakeConstraintRepository();
    // reference vacía => cliente_id 3 no existe
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'NUEVO', 'cliente_id' => 3], null);
    assert_same(['El valor seleccionado no es válido.'], $errors['cliente_id'] ?? []);
});

test('CrudDbConstraintValidator: exists passes when reference present', function (): void {
    $repo = new FakeConstraintRepository();
    $repo->reference['dom_demo_clientes.id.3'] = true;
    $validator = new CrudDbConstraintValidator($repo);

    $errors = $validator->validate(constraint_definition(), ['folio' => 'NUEVO', 'cliente_id' => 3], null);
    assert_same([], $errors);
});
