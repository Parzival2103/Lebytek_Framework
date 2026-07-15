<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;

/**
 * Rechaza una propuesta `pending` de reponderación de variantes (Task 8).
 *
 * Anti-deuda §E (Task 9): `UPDATE ... WHERE status='pending'` únicamente —
 * **nunca** toca `dom_mkt_variant_weights`. Una propuesta ya resuelta
 * (doble submit) lanza `\InvalidArgumentException` sin efecto.
 */
final class RejectVariantProposalUseCase
{
    public function __construct(
        private readonly VariantProposalRepositoryInterface $proposals,
    ) {
    }

    public function ejecutar(int $proposalId, int $userId): void
    {
        $proposal = $this->proposals->findById($proposalId);
        if ($proposal === null || ($proposal['status'] ?? null) !== 'pending') {
            throw new \InvalidArgumentException('La propuesta ya no está pendiente (doble envío o ya resuelta).');
        }

        if ($this->proposals->markRejected($proposalId, $userId) !== 1) {
            throw new \InvalidArgumentException('La propuesta ya fue resuelta (doble envío).');
        }
    }
}
