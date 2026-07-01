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
    ): void;

    public function markApiProvisionError(int $leadId, string $error): void;

    public function markApiDeprovisioned(int $leadId): void;

    /** @return list<array<string, mixed>> */
    public function findDemosOlderThanDays(int $days): array;
}
