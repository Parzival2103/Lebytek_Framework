<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Calendar\DateRange;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\CalendarEventSourceInterface;
use App\Kernel\Security\Session;

final class CrudResourceService implements CalendarEventSourceInterface
{
    public function __construct(
        private readonly CrudConfigLoader $configLoader,
        private readonly CrudDataService $dataService,
        private readonly CrudFormBuilder $formBuilder,
        private readonly CrudTableBuilder $tableBuilder,
        private readonly RbacService $rbacService,
        private readonly CrudActionResolver $actionResolver,
        private readonly CrudActionService $actionService,
        private readonly CrudDetailBuilder $detailBuilder,
        private readonly CrudScopeResolver $scopeResolver,
        private readonly CrudReturnUrlResolver $returnUrlResolver,
    ) {}

    public function resolveListReturnUrl(string $resource, ?string $candidate = null): string
    {
        return $this->returnUrlResolver->resolve($resource, $candidate);
    }

    private function assertOwnership(\App\Domain\Entities\CrudResourceDefinition $definition, array $row, ?int $userId): void
    {
        // Fuente única de verdad del bloqueo de propiedad (ver CrudScopeResolver::assertOwnedBy).
        // Tratar como inexistente para no revelar registros ajenos (404 en show/edit).
        $this->scopeResolver->assertOwnedBy(
            $definition,
            $row,
            $userId,
            fn(string $slug): bool => $this->rbacService->puede($slug)
        );
    }

    public function buildIndexData(string $resource, array $query, ?int $userId = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
        $result = $this->dataService->list($definition, $query, $userId, $can);
        $permissions = $this->resolvePermissions($definition->permissionPrefix());

        $data = $this->tableBuilder->build(
            definition: $definition,
            rows: $result['rows'],
            paginator: $result['paginator'],
            total: $result['total'],
            permissions: $permissions,
            query: $query,
            groupBy: (string) ($result['groupBy'] ?? ''),
            summaryRow: is_array($result['summaryRow'] ?? null) ? $result['summaryRow'] : [],
            aggregationSkipped: (bool) ($result['aggregationSkipped'] ?? false),
            aggregationSkipMessage: isset($result['aggregationSkipMessage']) ? (string) $result['aggregationSkipMessage'] : null,
            tableCompact: $definition->listTableCompact()
        );

        if (is_array($data['rows'] ?? null)) {
            foreach ($data['rows'] as &$row) {
                $row['_actions'] = $this->actionResolver->visibleRowActions($definition, $row, $can);
            }
            unset($row);
        }
        $data['bulkActions'] = $this->actionResolver->permittedBulkActions($definition, $can);
        $data['selectable'] = !empty($data['bulkActions']) && empty($data['grouped']);

        return $data;
    }

    /**
     * Feed de eventos para el módulo Calendario: aplica permiso `ver` y scope del
     * recurso, igual que el listado, y devuelve las filas crudas en el rango dado.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function eventosCalendario(
        string $resource,
        string $dateColumn,
        DateRange $range,
        ?int $userId,
        array $filters = []
    ): array {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);

        return $this->dataService->eventsInRange(
            $definition,
            $dateColumn,
            $range->from()->format('Y-m-d H:i:s'),
            $range->to()->format('Y-m-d H:i:s'),
            $userId,
            $can,
            $filters
        );
    }

    /**
     * @param array<string,mixed> $prefill valores iniciales (p. ej. desde el calendario),
     *                                      acotados a columnas de formulario declaradas.
     */
    public function buildCreateData(string $resource, array $prefill = []): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('crear'));

        $returnCandidate = $this->extractReturnCandidate($prefill);
        unset($prefill['return_to']);

        // El input previo (errores de validación) tiene prioridad sobre el prefill.
        $oldValues = Session::oldInput('_crud_values', []);
        $values = array_merge(
            $this->filterPrefill($definition, $prefill),
            is_array($oldValues) ? $oldValues : []
        );
        if ($returnCandidate === null) {
            $returnCandidate = $this->extractReturnCandidate(is_array($oldValues) ? $oldValues : []);
        }
        unset($values['return_to']);

        $data = $this->formBuilder->build(
            definition: $definition,
            values: $values,
            errors: Session::getFlash('errors', []),
            action: '/admin/crud/' . $definition->key(),
            isEdit: false
        );
        $data['returnUrl'] = $this->returnUrlResolver->resolve($resource, $returnCandidate);

        return $data;
    }

    /**
     * Acota un array de prefill a los nombres de campos de formulario declarados,
     * evitando inyectar claves arbitrarias en el formulario.
     *
     * @param array<string,mixed> $prefill
     * @return array<string,mixed>
     */
    private function filterPrefill(\App\Domain\Entities\CrudResourceDefinition $definition, array $prefill): array
    {
        if ($prefill === []) {
            return [];
        }
        $allowed = [];
        foreach ($definition->formFields() as $field) {
            $allowed[$field->name()] = true;
        }
        $out = [];
        foreach ($prefill as $key => $value) {
            $key = (string) $key;
            if (isset($allowed[$key]) && (is_scalar($value) || $value === null)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    public function store(string $resource, array $input, array $files, ?int $userId, string $ip): int
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('crear'));

        return $this->dataService->store($definition, $input, $files, $userId, $ip);
    }

    public function buildShowData(string $resource, int $id, ?int $userId = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }
        $this->assertOwnership($definition, $row, $userId);

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
        $actions = $this->actionResolver->visibleRowActions($definition, $row, $can);

        $state = null;
        $machine = $definition->stateMachine();
        if ($machine !== null) {
            $value = (string) ($row[$machine->column()] ?? '');
            $state = [
                'value' => $value,
                'label' => $machine->label($value) ?? $value,
                'badge' => $machine->badge($value) ?? 'secondary',
            ];
        }

        $tabs = $this->detailBuilder->build($definition, $row);

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'row' => $row,
            'columns' => $definition->listColumns(),
            'primaryKey' => $definition->primaryKey(),
            'actions' => $actions,
            'state' => $state,
            'tabs' => $tabs,
        ];
    }

    public function buildEditData(string $resource, int $id, ?int $userId = null, ?string $returnTo = null): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('editar'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }
        $this->assertOwnership($definition, $row, $userId);

        $oldValues = Session::oldInput('_crud_values');
        $values = is_array($oldValues) ? $oldValues : $row;
        $returnCandidate = $returnTo !== null && $returnTo !== ''
            ? $returnTo
            : $this->extractReturnCandidate(is_array($oldValues) ? $oldValues : []);
        unset($values['return_to']);

        $data = $this->formBuilder->build(
            definition: $definition,
            values: $values,
            errors: Session::getFlash('errors', []),
            action: '/admin/crud/' . $definition->key() . '/' . $id,
            isEdit: true
        );
        $data['returnUrl'] = $this->returnUrlResolver->resolve($resource, $returnCandidate);

        return $data;
    }

    /**
     * @param array<string,mixed> $input
     */
    private function extractReturnCandidate(array $input): ?string
    {
        if (!isset($input['return_to'])) {
            return null;
        }

        $value = trim((string) $input['return_to']);

        return $value !== '' ? $value : null;
    }

    public function update(string $resource, int $id, array $input, array $files, ?int $userId, string $ip): void
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('editar'));

        $existing = $this->dataService->find($definition, $id);
        if ($existing === null || (int) ($existing['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertOwnership($definition, $existing, $userId);

        $this->dataService->update($definition, $id, $input, $files, $userId, $ip);
    }

    public function delete(string $resource, int $id, ?int $userId, string $ip): void
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('eliminar'));

        $existing = $this->dataService->find($definition, $id);
        if ($existing === null || (int) ($existing['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertOwnership($definition, $existing, $userId);

        $this->dataService->delete($definition, $id, $userId, $ip);
    }

    /** @param array<string, mixed> $input */
    public function runAction(string $resource, int $id, string $action, array $input, ?int $userId, string $ip): void
    {
        $this->actionService->run($resource, $id, $action, $input, $userId, $ip);
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $input
     * @return array{ok: int, fail: int, errors: list<string>}
     */
    public function runBulkAction(string $resource, string $action, array $ids, array $input, ?int $userId, string $ip): array
    {
        return $this->actionService->runBulk($resource, $action, $ids, $input, $userId, $ip);
    }

    private function resolvePermissions(string $prefix): array
    {
        return [
            'ver' => $this->rbacService->puede($prefix . '.ver'),
            'crear' => $this->rbacService->puede($prefix . '.crear'),
            'editar' => $this->rbacService->puede($prefix . '.editar'),
            'eliminar' => $this->rbacService->puede($prefix . '.eliminar'),
        ];
    }
}
