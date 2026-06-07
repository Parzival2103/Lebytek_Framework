<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Exceptions\ValidationException;

/**
 * Lógica pura de resolución de acciones: produce view-models para render y
 * resuelve acciones por nombre para ejecución. No toca DB ni RBAC directamente;
 * recibe un callable `can(slug): bool` para filtrar por permiso.
 */
final class CrudActionResolver
{
    /**
     * @param array<string, mixed> $row
     * @param callable(string): bool $can
     * @return list<array<string, mixed>>
     */
    public function visibleRowActions(CrudResourceDefinition $definition, array $row, callable $can): array
    {
        $id = (int) ($row[$definition->primaryKey()] ?? 0);
        $prefix = $definition->permissionPrefix();
        $out = [];

        foreach ($definition->rowActions() as $action) {
            if (!$action->isVisibleFor($row)) {
                continue;
            }
            $permission = $action->resolvePermission($prefix);
            if ($permission !== null && !$can($permission)) {
                continue;
            }
            $out[] = $this->rowViewModel($definition, $action, $id, $row, $can);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param callable(string): bool $can
     * @return array<string, mixed>
     */
    private function rowViewModel(CrudResourceDefinition $definition, CrudActionDefinition $action, int $id, array $row, callable $can): array
    {
        $vm = [
            'name' => $action->name(),
            'type' => $action->type(),
            'label' => $action->label(),
            'icon' => $action->icon(),
            'method' => $action->method(),
            'confirm' => $action->confirm(),
            'enabled' => $action->isEnabledFor($row),
        ];

        if ($action->isBuiltin()) {
            $vm['href'] = $this->builtinHref($definition->key(), $action->name(), $id);
        } elseif ($action->isLink()) {
            $vm['href'] = str_replace('{id}', (string) $id, (string) $action->route());
        } else { // handler / transition
            $vm['endpoint'] = '/admin/crud/' . $definition->key() . '/' . $id . '/accion/' . $action->name();
        }

        return $vm;
    }

    private function builtinHref(string $resourceKey, string $name, int $id): string
    {
        $base = '/admin/crud/' . $resourceKey;
        return match ($name) {
            'show'   => $base . '/' . $id,
            'edit'   => $base . '/' . $id . '/editar',
            'delete' => $base . '/' . $id . '/eliminar',
            default  => $base . '/' . $id,
        };
    }

    /**
     * @param callable(string): bool $can
     * @return list<array<string, mixed>>
     */
    public function permittedBulkActions(CrudResourceDefinition $definition, callable $can): array
    {
        $prefix = $definition->permissionPrefix();
        $out = [];
        foreach ($definition->bulkActions() as $action) {
            $permission = $action->resolvePermission($prefix);
            if ($permission !== null && !$can($permission)) {
                continue;
            }
            $out[] = [
                'name' => $action->name(),
                'label' => $action->label(),
                'confirm' => $action->confirm(),
                'endpoint' => '/admin/crud/' . $definition->key() . '/accion-masiva/' . $action->name(),
            ];
        }
        return $out;
    }

    public function resolveExecutable(CrudResourceDefinition $definition, string $actionName): CrudActionDefinition
    {
        foreach ($definition->rowActions() as $action) {
            if ($action->name() === $actionName) {
                return $action;
            }
        }
        throw new ValidationException("La acción '{$actionName}' no existe en este recurso.");
    }

    public function resolveBulkExecutable(CrudResourceDefinition $definition, string $actionName): CrudActionDefinition
    {
        foreach ($definition->bulkActions() as $action) {
            if ($action->name() === $actionName) {
                return $action;
            }
        }
        throw new ValidationException("La acción masiva '{$actionName}' no existe en este recurso.");
    }
}
