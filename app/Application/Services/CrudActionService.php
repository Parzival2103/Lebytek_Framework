<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Context\CrudActionContext;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Kernel\Logging\AppLogger;

/**
 * Ejecuta acciones de fila y masivas del CRUD Engine. La decisión de despacho
 * (por tipo) vive en `dispatch()` y es el punto testeable; `run()`/`runBulk()`
 * orquestan carga de definición, RBAC, carga de registro, re-chequeo de
 * `visible_when` en servidor y auditoría.
 *
 * Tipos soportados en Fase 1: `handler`. `link` es solo navegación (no se
 * ejecuta en servidor). `builtin` usa las rutas existentes. `transition` llega
 * en Fase 2.
 */
final class CrudActionService
{
    private const MAX_BULK_IDS = 500;

    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry,
        private readonly ?CrudConfigLoader $configLoader = null,
        private readonly ?CrudDataService $dataService = null,
        private readonly ?CrudActionResolver $resolver = null,
        private readonly ?RbacService $rbacService = null,
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null,
        private readonly ?CrudTransitionService $transitionService = null,
        private readonly ?CrudScopeResolver $scopeResolver = null
    ) {}

    /**
     * Bloqueo server-side de propiedad para acciones, idéntico en lógica a
     * CrudResourceService::assertOwnership (ambos delegan en
     * CrudScopeResolver::assertOwnedBy). Si el recurso no declara owner scope o
     * faltan dependencias, retorna sin efecto (comportamiento sin cambios).
     *
     * @param array<string, mixed> $record
     */
    private function assertActionOwnership(CrudResourceDefinition $definition, array $record, ?int $userId): void
    {
        if ($this->scopeResolver === null || $this->rbacService === null) {
            return;
        }
        $this->scopeResolver->assertOwnedBy(
            $definition,
            $record,
            $userId,
            fn(string $slug): bool => $this->rbacService->puede($slug)
        );
    }

    /**
     * Despacha una acción ya resuelta sobre un contexto ya construido.
     * Único punto que toca el registry; sin DB → unit-testable.
     */
    public function dispatch(CrudActionDefinition $action, CrudActionContext $ctx): void
    {
        if (!$action->isHandler()) {
            throw new ValidationException("La acción '{$action->name()}' no es ejecutable en el servidor (tipo {$action->type()}).");
        }

        $handler = $this->handlerRegistry->resolve($action->handler(), CrudActionHandlerInterface::class);
        if ($handler === null) {
            throw new ValidationException("El handler '{$action->handler()}' no está registrado en la whitelist.");
        }

        /** @var CrudActionHandlerInterface $handler */
        $handler->handle($ctx);
    }

    /**
     * Ejecuta una acción de fila completa.
     *
     * @param array<string, mixed> $input
     */
    public function run(string $resource, int $id, string $actionName, array $input, ?int $userId, string $ip): void
    {
        $this->assertWired();
        $definition = $this->configLoader->load($resource);
        $action = $this->resolver->resolveExecutable($definition, $actionName);

        $permission = $action->resolvePermission($definition->permissionPrefix());
        if ($permission !== null) {
            $this->rbacService->verificar($permission);
        }

        $record = $this->dataService->find($definition, $id);
        if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
            throw new ValidationException('El registro solicitado no existe.');
        }

        $this->assertActionOwnership($definition, $record, $userId);

        // Re-chequeo server-side: nunca confiar en la UI.
        if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
            throw new ValidationException('La acción no está disponible para este registro.');
        }

        if ($action->isTransition()) {
            if ($this->transitionService === null) {
                throw new \LogicException('CrudActionService no tiene CrudTransitionService cableado.');
            }
            $this->transitionService->apply($definition, $action, $record, $userId, $ip);
            return;
        }

        $ctx = new CrudActionContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $id,
            $record,
            $action->name(),
            $input
        );

        $this->dispatch($action, $ctx);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.action:' . $action->name(),
            $definition->table(),
            $id,
            json_encode(['action' => $action->name(), 'input' => $input], JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );
    }

    /**
     * Ejecuta una acción masiva best-effort. Devuelve resumen {ok, fail, errors}.
     *
     * @param list<int> $ids
     * @param array<string, mixed> $input
     * @return array{ok: int, fail: int, errors: list<string>}
     */
    public function runBulk(string $resource, string $actionName, array $ids, array $input, ?int $userId, string $ip): array
    {
        $this->assertWired();
        $definition = $this->configLoader->load($resource);
        $action = $this->resolver->resolveBulkExecutable($definition, $actionName);

        $permission = $action->resolvePermission($definition->permissionPrefix());
        if ($permission !== null) {
            $this->rbacService->verificar($permission);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
        if (count($ids) > self::MAX_BULK_IDS) {
            throw new ValidationException('Demasiados registros seleccionados (máximo ' . self::MAX_BULK_IDS . ').');
        }

        $ok = 0;
        $fail = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                $record = $this->dataService->find($definition, $id);
                if (!is_array($record) || (int) ($record['deleted'] ?? 0) === 1) {
                    throw new ValidationException("Registro {$id} no existe.");
                }
                $this->assertActionOwnership($definition, $record, $userId);
                $ctx = new CrudActionContext(
                    $definition->key(),
                    $definition->table(),
                    $definition->primaryKey(),
                    $userId,
                    $ip,
                    $id,
                    $record,
                    $action->name(),
                    $input
                );
                $this->dispatch($action, $ctx);
                $this->bitacoraRepository->registrar(
                    $userId,
                    'crud.action:' . $action->name(),
                    $definition->table(),
                    $id,
                    json_encode(['bulk' => true, 'action' => $action->name()], JSON_UNESCAPED_UNICODE) ?: '',
                    $ip
                );
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $errors[] = "ID {$id}: " . $e->getMessage();
                AppLogger::warning('CRUD bulk action: ítem falló', [
                    'resource' => $resource, 'action' => $actionName, 'id' => $id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'errors' => $errors];
    }

    private function assertWired(): void
    {
        if ($this->configLoader === null || $this->dataService === null
            || $this->resolver === null || $this->rbacService === null
            || $this->bitacoraRepository === null) {
            throw new \LogicException('CrudActionService no está cableado con todas sus dependencias.');
        }
    }
}
