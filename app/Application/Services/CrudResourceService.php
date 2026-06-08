<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;
use App\Kernel\Security\Session;

final class CrudResourceService
{
    public function __construct(
        private readonly CrudConfigLoader $configLoader,
        private readonly CrudDataService $dataService,
        private readonly CrudFormBuilder $formBuilder,
        private readonly CrudTableBuilder $tableBuilder,
        private readonly RbacService $rbacService,
        private readonly CrudActionResolver $actionResolver,
        private readonly CrudActionService $actionService
    ) {}

    public function buildIndexData(string $resource, array $query): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $result = $this->dataService->list($definition, $query);
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

        $can = fn(string $slug): bool => $this->rbacService->puede($slug);
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

    public function buildCreateData(string $resource): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('crear'));

        return $this->formBuilder->build(
            definition: $definition,
            values: Session::oldInput('_crud_values', []),
            errors: Session::getFlash('errors', []),
            action: '/admin/crud/' . $definition->key(),
            isEdit: false
        );
    }

    public function store(string $resource, array $input, array $files, ?int $userId, string $ip): int
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('crear'));

        return $this->dataService->store($definition, $input, $files, $userId, $ip);
    }

    public function buildShowData(string $resource, int $id): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('ver'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

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

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'row' => $row,
            'columns' => $definition->listColumns(),
            'primaryKey' => $definition->primaryKey(),
            'actions' => $actions,
            'state' => $state,
        ];
    }

    public function buildEditData(string $resource, int $id): array
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('editar'));

        $row = $this->dataService->find($definition, $id);
        if ($row === null || (int) ($row['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $oldValues = Session::oldInput('_crud_values');
        $values = is_array($oldValues) ? $oldValues : $row;

        return $this->formBuilder->build(
            definition: $definition,
            values: $values,
            errors: Session::getFlash('errors', []),
            action: '/admin/crud/' . $definition->key() . '/' . $id,
            isEdit: true
        );
    }

    public function update(string $resource, int $id, array $input, array $files, ?int $userId, string $ip): void
    {
        $definition = $this->configLoader->load($resource);
        $this->rbacService->verificar($definition->permissionFor('editar'));

        $existing = $this->dataService->find($definition, $id);
        if ($existing === null || (int) ($existing['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

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
