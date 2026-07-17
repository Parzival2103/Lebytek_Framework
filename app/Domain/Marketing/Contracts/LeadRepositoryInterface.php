<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;

interface LeadRepositoryInterface
{
    /**
     * Persiste un lead y devuelve su id.
     *
     * @param array{token:string,code_hash:string,expires_at:string}|null $emailVerification
     */
    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByEmailVerifyToken(string $token): ?array;

    public function incrementEmailVerifyAttempts(int $leadId): void;

    public function markEmailVerified(int $leadId): void;

    public function markApiProvisioned(
        int $leadId,
        string $tenantPublicId,
        string $externalRef,
        string $instancePublicId = '',
        ?int $paqueteId = null,
        string $planSlug = 'demo',
        int $demoDays = 30,
    ): void;

    public function markApiProvisionError(int $leadId, string $error): void;

    /** DELETE aceptado en API; baja async en Green — conserva refs para confirmación. */
    public function markApiDeprovisionInitiated(int $leadId): void;

    /** Instancias confirmadas eliminadas en la API. */
    public function markApiDeprovisionCompleted(int $leadId): void;

    /** @return list<array<string, mixed>> */
    public function findDemosOlderThanDays(int $days): array;

    /** @return list<array<string, mixed>> */
    public function findDemosExpired(): array;

    /** @return list<array<string, mixed>> */
    public function findPendingDeprovisions(): array;

    /** @return array<string, mixed>|null */
    public function findDemoPackageBySlug(string $slug): ?array;

    /** @return array<string, mixed>|null */
    public function findLatestByEmail(string $email): ?array;

    public function markConverted(int $leadId, string $planSlug, ?int $paqueteId = null): void;

    public function markCancelled(int $leadId): void;

    public function clearCancelled(int $leadId): void;
}
