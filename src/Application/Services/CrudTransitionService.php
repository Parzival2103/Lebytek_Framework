<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;

/**
 * Aplica transiciones de estado del CRUD Engine.
 *
 * `authorize()` es el seam puro (sin DB): valida la transición contra la
 * máquina de estados y, si se declara, corre el guard whitelisteado. Lanza para
 * bloquear. `apply()` (Task 6) añade la persistencia + bitácora `crud.transition`
 * + eventos `beforeTransition`/`afterTransition`. Las deps de DB son opcionales
 * para que las pruebas unitarias de `authorize()` construyan solo con el registry.
 */
final class CrudTransitionService
{
    public function __construct(
        private readonly CrudHandlerRegistry $handlerRegistry,
        private readonly ?GenericCrudRepository $repository = null,
        private readonly ?BitacoraRepositoryInterface $bitacoraRepository = null,
        private readonly ?CrudHookRunner $hookRunner = null
    ) {}

    /**
     * Seam puro: valida `from → to` contra la máquina y corre el guard opcional.
     * No toca la DB. Lanza ValidationException (transición inválida / guard
     * ausente) o lo que lance el guard (regla de negocio) para bloquear.
     */
    public function authorize(CrudStateMachine $machine, ?string $guardKey, CrudTransitionContext $ctx): void
    {
        if (!$machine->canTransition($ctx->from(), $ctx->to())) {
            throw new ValidationException("Transición no permitida: '{$ctx->from()}' → '{$ctx->to()}'.");
        }

        if ($guardKey !== null && $guardKey !== '') {
            $guard = $this->handlerRegistry->resolve($guardKey, CrudTransitionGuardInterface::class);
            if ($guard === null) {
                throw new ValidationException("El guard '{$guardKey}' no está registrado en la whitelist.");
            }
            /** @var CrudTransitionGuardInterface $guard */
            $guard->authorize($ctx);
        }
    }

    /**
     * Flujo completo de transición: valida (authorize) → evento beforeTransition
     * → persiste la columna de estado (+ updated_at/by) → bitácora crud.transition
     * → evento afterTransition. Valida ANTES de tocar la DB, así que las rutas de
     * fallo no requieren repositorio.
     *
     * @param array<string, mixed> $record fila actual del registro (incluye PK + estado)
     */
    public function apply(
        CrudResourceDefinition $definition,
        CrudActionDefinition $action,
        array $record,
        ?int $userId,
        string $ip
    ): void {
        $machine = $definition->stateMachine();
        if ($machine === null) {
            throw new ValidationException('El recurso no define una máquina de estados.');
        }

        $column = $machine->column();
        $from = (string) ($record[$column] ?? '');
        $to = (string) ($action->to() ?? '');
        $id = (int) ($record[$definition->primaryKey()] ?? 0);

        $ctx = new CrudTransitionContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $record,
            $column,
            $from,
            $to,
            []
        );

        // Lanza para bloquear (transición inválida / guard). Antes de cualquier DB.
        $this->authorize($machine, $action->guard(), $ctx);

        if ($this->repository === null || $this->bitacoraRepository === null) {
            throw new \LogicException('CrudTransitionService no está cableado para persistir.');
        }

        if ($this->hookRunner !== null) {
            $this->hookRunner->run($definition, 'beforeTransition', $ctx);
        }

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, [
            $column => $to,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ]);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.transition',
            $definition->table(),
            $id,
            json_encode(['from' => $from, 'to' => $to, 'action' => $action->name()], JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        if ($this->hookRunner !== null) {
            $this->hookRunner->run($definition, 'afterTransition', $ctx);
        }
    }
}
