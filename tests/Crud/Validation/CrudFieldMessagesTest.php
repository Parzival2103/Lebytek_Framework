<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudFieldValidationService;
use Lebytek\Framework\Domain\Entities\CrudFieldDefinition;

function field_with(array $data): CrudFieldDefinition
{
    return CrudFieldDefinition::fromArray($data);
}

test('CrudFieldValidationService: custom message overrides the default for required', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with([
        'name' => 'codigo', 'label' => 'Código', 'type' => 'text', 'required' => true,
        'validation' => ['messages' => ['required' => 'El código es obligatorio']],
    ]);
    $errors = $svc->validateValue($field, '');
    assert_same(['El código es obligatorio'], $errors);
});

test('CrudFieldValidationService: default message is used when no override exists', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with(['name' => 'codigo', 'label' => 'Código', 'type' => 'text', 'required' => true]);
    $errors = $svc->validateValue($field, '');
    assert_same(['Este campo es obligatorio.'], $errors);
});

test('CrudFieldValidationService: custom message overrides maxlength', function (): void {
    $svc = new CrudFieldValidationService();
    $field = field_with([
        'name' => 'codigo', 'label' => 'Código', 'type' => 'text',
        'validation' => ['maxlength' => 3, 'messages' => ['maxlength' => 'Máximo 3 caracteres']],
    ]);
    $errors = $svc->validateValue($field, 'ABCD');
    assert_same(['Máximo 3 caracteres'], $errors);
});
