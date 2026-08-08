<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

function crud_p02_invoke_build_payload(CrudDataService $service, CrudResourceDefinition $def, array $input): array
{
    $m = new ReflectionMethod(CrudDataService::class, 'buildPayload');
    $m->setAccessible(true);

    return $m->invoke($service, $def, $input, [], true, null, 1, '127.0.0.1');
}

function crud_p02_def_with_status_in_form(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'demo_productos',
            'title' => 'Demo',
            'table' => 'dom_demo_productos',
            'primary_key' => 'id',
            'permission_prefix' => 'demo_productos',
        ],
        'states' => [
            'column' => 'status',
            'values' => [
                'activo' => ['label' => 'Activo'],
                'inactivo' => ['label' => 'Inactivo'],
            ],
            'transitions' => ['activo' => ['inactivo'], 'inactivo' => ['activo']],
        ],
        'form' => [
            'fields' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                [
                    'name' => 'status',
                    'label' => 'Estado',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
                ],
            ],
        ],
    ]);
}

test('buildPayload omite states.column aunque venga en form+input (C3 defensa)', function (): void {
    // CrudDataService es final; patrón idéntico a tests/Crud/Upload/CrudUploadLedgerTest.php.
    // buildPayload tolera dbConstraintValidator/handlerRegistry null; solo necesita fieldValidation.
    $ref = new ReflectionClass(CrudDataService::class);
    $service = $ref->newInstanceWithoutConstructor();
    foreach (['fieldValidation', 'dbConstraintValidator', 'handlerRegistry'] as $propName) {
        $prop = $ref->getProperty($propName);
        $prop->setAccessible(true);
        $prop->setValue(
            $service,
            $propName === 'fieldValidation'
                ? new \Lebytek\Framework\Application\Services\CrudFieldValidationService()
                : null
        );
    }

    $payload = crud_p02_invoke_build_payload(
        $service,
        crud_p02_def_with_status_in_form(),
        ['nombre' => 'X', 'status' => 'inactivo']
    );
    assert_true(!array_key_exists('status', $payload), 'status no debe persistirse vía form');
    assert_same('X', $payload['nombre'] ?? null);
});
