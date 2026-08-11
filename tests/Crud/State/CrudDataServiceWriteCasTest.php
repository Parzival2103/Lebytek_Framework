<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Application\Services\CrudFieldValidationService;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudHookRunner;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

require_once dirname(__DIR__, 2) . '/fixtures/fake_crud_repository.php';

final class CrudDataServiceWriteCasBitacora implements BitacoraRepositoryInterface
{
    public int $calls = 0;

    public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void
    {
        $this->calls++;
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

function crud_data_service_write_cas(FakeCrudRepository $repo, CrudDataServiceWriteCasBitacora $bit): CrudDataService
{
    $ref = new ReflectionClass(CrudDataService::class);
    $service = $ref->newInstanceWithoutConstructor();
    foreach ([
        'repository' => $repo,
        'bitacoraRepository' => $bit,
        'hookRunner' => new CrudHookRunner(new CrudHandlerRegistry([])),
        'fieldValidation' => new CrudFieldValidationService(),
    ] as $propName => $value) {
        $prop = $ref->getProperty($propName);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    }

    return $service;
}

function crud_data_service_write_cas_definition(): CrudResourceDefinition
{
    return CrudResourceDefinition::fromArray([
        'resource' => [
            'key' => 'x',
            'title' => 'X',
            'table' => 'dom_x',
            'primary_key' => 'id',
            'permission_prefix' => 'x',
        ],
        'form' => ['fields' => []],
    ]);
}

test('delete on already-deleted row throws conflict and does not mutate', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'deleted' => 1];
    $bit = new CrudDataServiceWriteCasBitacora();
    $svc = crud_data_service_write_cas($repo, $bit);
    $def = crud_data_service_write_cas_definition();

    $caught = null;
    try {
        $svc->delete($def, 1, 7, '127.0.0.1');
    } catch (ValidationException $e) {
        $caught = $e;
    }

    assert_true($caught instanceof ValidationException);
    assert_same('El registro cambió; recarga e inténtalo de nuevo.', $caught->getMessage());
    assert_same(1, (int) ($repo->rowsById[1]['deleted'] ?? 0));
    assert_same(0, $bit->calls, 'no bitácora on conflict');
    assert_same(['deleted' => 0], $repo->updateCalls[0]['expected'] ?? null);
});

test('delete on active row soft-deletes with CAS deleted=0', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'deleted' => 0];
    $bit = new CrudDataServiceWriteCasBitacora();
    $svc = crud_data_service_write_cas($repo, $bit);
    $def = crud_data_service_write_cas_definition();

    $svc->delete($def, 1, 7, '127.0.0.1');

    assert_same(1, (int) ($repo->rowsById[1]['deleted'] ?? 0));
    assert_same(1, $bit->calls);
    assert_same(['deleted' => 0], $repo->updateCalls[0]['expected'] ?? null);
});
