<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudFieldValidationService;
use Lebytek\Framework\Domain\Entities\CrudFieldDefinition;

function crud_p02_select_field(array $data): CrudFieldDefinition
{
    return CrudFieldDefinition::fromArray($data);
}

test('select con options rechaza valor fuera de allowlist (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'required' => true,
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
    ]);
    $errors = $svc->validateValue($field, 'hacked');
    assert_same(['Valor no permitido.'], $errors);
});

test('select con options acepta clave válida (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
    ]);
    assert_same([], $svc->validateValue($field, 'activo'));
});

test('validation.in explícito tiene prioridad sobre options (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'status',
        'label' => 'Estado',
        'type' => 'select',
        'options' => [
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
        ],
        'validation' => [
            'in' => ['activo'],
            'messages' => ['in' => 'Solo activo'],
        ],
    ]);
    assert_same(['Solo activo'], $svc->validateValue($field, 'inactivo'));
    assert_same([], $svc->validateValue($field, 'activo'));
});

test('select sin options no inventa allowlist (G6)', function (): void {
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'color',
        'label' => 'Color',
        'type' => 'select',
    ]);
    assert_same([], $svc->validateValue($field, 'cualquier-cosa'));
});

test('relation no usa options del field como in automático (G6 scope)', function (): void {
    // Las options de relation se resuelven en FormBuilder vía CrudRelationService;
    // el field JSON no las trae. exists sigue siendo la regla de integridad (punto 7 soft-delete).
    $svc = new CrudFieldValidationService();
    $field = crud_p02_select_field([
        'name' => 'categoria_id',
        'label' => 'Categoría',
        'type' => 'relation',
        'relation' => 'categoria',
        'options' => ['1' => 'A', '2' => 'B'], // si alguien las pone, no las tratamos como select auto-in
    ]);
    assert_same([], $svc->validateValue($field, '999'));
});
