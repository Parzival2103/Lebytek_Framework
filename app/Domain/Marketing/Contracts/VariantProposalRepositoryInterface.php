<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

/**
 * Propuestas de reponderación de variantes (`dom_mkt_variant_proposals`).
 *
 * Valores válidos de la columna `status`: `pending`|`accepted`|`rejected`|`superseded`.
 */
interface VariantProposalRepositoryInterface
{
    /** @param array<string, mixed> $payload */
    public function insertPending(array $payload): int;

    /** @return list<array<string, mixed>> */
    public function findPending(): array;

    /**
     * Última `pending` (la más reciente por `created_at`), o `null` si no
     * hay ninguna. Anti-deuda §E — a lo sumo una `pending` a la vez; usada
     * por `ComputeVariantScoresUseCase` para deduplicar contra propuestas
     * idénticas antes de `supersedeAllPending()` + `insertPending()`.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestPending(): ?array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /**
     * Acepta la propuesta `$id` (Anti-deuda §E — Task 9): dentro de una única
     * transacción, `UPDATE ... WHERE id=? AND status='pending'`; si el
     * `rowCount` es `1`, ejecuta `$applyWeights` (upserts de
     * `dom_mkt_variant_weights`) y hace commit; si es `0` (ya no pending —
     * doble submit o superseded), revierte y **no** invoca `$applyWeights`.
     * Nunca deja `accepted` sin pesos aplicados ni pesos aplicados sin
     * `accepted`. Devuelve `true` si se aplicó, `false` si ya no era pending.
     *
     * @param callable(): void $applyWeights
     */
    public function acceptAtomically(int $id, int $userId, callable $applyWeights): bool;

    /**
     * `UPDATE ... WHERE id=? AND status='pending'` únicamente; nunca toca
     * pesos. Devuelve el número de filas afectadas (`0` si ya no era
     * pending — doble submit o superseded).
     */
    public function markRejected(int $id, int $userId): int;

    /** Marca todas las `pending` como `superseded`; retorna el número de filas afectadas. */
    public function supersedeAllPending(): int;
}
