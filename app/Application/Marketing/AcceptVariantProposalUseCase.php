<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;

/**
 * Aplica una propuesta `pending` de reponderación de variantes (Task 8) a
 * `dom_mkt_variant_weights`, cerrando la propuesta como `accepted`.
 *
 * Anti-deuda §E + §S (Task 9):
 * - Solo acepta propuestas `pending`; una propuesta ya resuelta (doble
 *   submit) lanza `\InvalidArgumentException` sin mutar pesos.
 * - Compara `payload.current_weights` (snapshot al momento de calcular la
 *   propuesta) contra los pesos vigentes (`VariantWeightRepositoryInterface::all()`);
 *   si algún slug difiere más de `STALE_TOLERANCE`, rechaza como stale —
 *   ops debe recalcular o rechazar la propuesta en vez de aplicarla a
 *   ciegas sobre pesos que ya cambiaron.
 * - `suggested_weights` se normaliza para sumar `1.0` antes de aplicar
 *   (rechaza si la suma es `<= 0`).
 * - El `UPDATE ... WHERE status='pending'` y los `upsert()` de pesos
 *   ocurren dentro de una única transacción
 *   (`VariantProposalRepositoryInterface::acceptAtomically`): nunca queda
 *   `accepted` sin pesos aplicados ni pesos aplicados sin `accepted`.
 */
final class AcceptVariantProposalUseCase
{
    private const STALE_TOLERANCE = 1e-4;

    public function __construct(
        private readonly VariantProposalRepositoryInterface $proposals,
        private readonly VariantWeightRepositoryInterface $weights,
    ) {
    }

    public function ejecutar(int $proposalId, int $userId): void
    {
        $proposal = $this->proposals->findById($proposalId);
        if ($proposal === null || ($proposal['status'] ?? null) !== 'pending') {
            throw new \InvalidArgumentException('La propuesta ya no está pendiente (doble envío o ya resuelta).');
        }

        $payload = is_array($proposal['payload'] ?? null) ? $proposal['payload'] : [];
        $snapshot = is_array($payload['current_weights'] ?? null) ? $payload['current_weights'] : [];
        $this->assertNotStale($snapshot);

        $suggested = is_array($payload['suggested_weights'] ?? null) ? $payload['suggested_weights'] : [];
        $normalized = $this->normalize($suggested);

        $accepted = $this->proposals->acceptAtomically($proposalId, $userId, function () use ($normalized): void {
            foreach ($normalized as $slug => $weight) {
                $this->weights->upsert((string) $slug, $weight);
            }
        });

        if (!$accepted) {
            throw new \InvalidArgumentException('La propuesta ya fue resuelta (doble envío).');
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function assertNotStale(array $snapshot): void
    {
        $live = $this->weights->all();

        foreach ($snapshot as $slug => $value) {
            $current = $live[(string) $slug] ?? $this->weights->get((string) $slug);
            if ($current === null || abs((float) $current - (float) $value) > self::STALE_TOLERANCE) {
                throw new \InvalidArgumentException(
                    "Los pesos vigentes cambiaron desde que se generó la propuesta (drift en '{$slug}'). Recalcula o rechaza."
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $suggested
     * @return array<string, float>
     */
    private function normalize(array $suggested): array
    {
        $floats = [];
        foreach ($suggested as $slug => $value) {
            $floats[(string) $slug] = (float) $value;
        }

        $sum = array_sum($floats);
        if ($sum <= 0.0) {
            throw new \InvalidArgumentException('suggested_weights inválido: la suma debe ser mayor a 0.');
        }

        foreach ($floats as $slug => $value) {
            $floats[$slug] = $value / $sum;
        }

        return $floats;
    }
}
