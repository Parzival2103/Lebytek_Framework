<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;

interface LeadRepositoryInterface
{
    /** Persiste un lead y devuelve su id. */
    public function guardar(LeadDraft $draft): int;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    public function markApiProvisioned(
        int $leadId,
        string $tenantPublicId,
        string $externalRef,
        string $instancePublicId = '',
    ): void;

    public function markApiProvisionError(int $leadId, string $error): void;

    /** DELETE aceptado en API; baja async en Green — conserva refs para confirmación. */
    public function markApiDeprovisionInitiated(int $leadId): void;

    /** Instancias confirmadas eliminadas en la API. */
    public function markApiDeprovisionCompleted(int $leadId): void;

    /** @return list<array<string, mixed>> */
    public function findDemosOlderThanDays(int $days): array;

    /** @return list<array<string, mixed>> */
    public function findPendingDeprovisions(): array;
}
