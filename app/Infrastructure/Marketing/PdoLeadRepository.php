<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\LeadApiLifecycleStatus;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoLeadRepository implements LeadRepositoryInterface
{
    public function guardar(LeadDraft $draft): int
    {
        $pdo = Connection::getInstance();
        $utm = $draft->utm();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_leads (nombre, email, telefono, mensaje, estado, utm_source, utm_medium, utm_campaign)
             VALUES (:nombre, :email, :telefono, :mensaje, :estado, :s, :m, :c)'
        );
        $stmt->execute([
            'nombre'   => $draft->nombre(),
            'email'    => $draft->email(),
            'telefono' => $draft->telefono(),
            'mensaje'  => $draft->mensaje(),
            'estado'   => 'pendiente',
            's'        => $utm['utm_source']   ?? null,
            'm'        => $utm['utm_medium']   ?? null,
            'c'        => $utm['utm_campaign'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_leads WHERE id = :id AND deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markApiProvisioned(
        int $leadId,
        string $tenantPublicId,
        string $externalRef,
        string $instancePublicId = '',
        ?int $paqueteId = null,
        string $planSlug = 'demo',
        int $demoDays = 30,
    ): void {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_leads
             SET api_tenant_public_id = :public_id,
                 api_instance_public_id = :instance_public_id,
                 external_ref = :external_ref,
                 api_provisioned_at = NOW(),
                 demo_started_at = NOW(),
                 demo_expires_at = DATE_ADD(NOW(), INTERVAL :demo_days DAY),
                 paquete_id = :paquete_id,
                 plan_slug = :plan_slug,
                 api_provision_error = NULL,
                 api_lifecycle_status = :lifecycle,
                 estado = :estado,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue('public_id', $tenantPublicId);
        $stmt->bindValue('instance_public_id', $instancePublicId !== '' ? $instancePublicId : null);
        $stmt->bindValue('external_ref', $externalRef);
        $stmt->bindValue('demo_days', $demoDays, \PDO::PARAM_INT);
        $stmt->bindValue('paquete_id', $paqueteId, $paqueteId !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue('plan_slug', $planSlug);
        $stmt->bindValue('lifecycle', LeadApiLifecycleStatus::PROVISION_INITIATED);
        $stmt->bindValue('estado', 'demo_enviada');
        $stmt->bindValue('id', $leadId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markApiProvisionError(int $leadId, string $error): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_leads
             SET api_provision_error = :error,
                 api_lifecycle_status = :lifecycle,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'error'     => $error,
            'lifecycle' => LeadApiLifecycleStatus::NONE,
            'id'        => $leadId,
        ]);
    }

    public function markApiDeprovisionInitiated(int $leadId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_leads
             SET api_provision_error = NULL,
                 api_lifecycle_status = :lifecycle,
                 estado = :estado,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'lifecycle' => LeadApiLifecycleStatus::DEPROVISION_INITIATED,
            'estado'    => 'demo_baja_pendiente',
            'id'        => $leadId,
        ]);
    }

    public function markApiDeprovisionCompleted(int $leadId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_leads
             SET api_tenant_public_id = NULL,
                 api_instance_public_id = NULL,
                 external_ref = NULL,
                 api_provisioned_at = NULL,
                 api_provision_error = NULL,
                 api_lifecycle_status = :lifecycle,
                 estado = :estado,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'lifecycle' => LeadApiLifecycleStatus::DEPROVISIONED,
            'estado'    => 'demo_baja',
            'id'        => $leadId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function findPendingDeprovisions(): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM dom_mkt_leads
             WHERE deleted = 0
               AND api_lifecycle_status = :lifecycle
               AND api_tenant_public_id IS NOT NULL'
        );
        $stmt->execute(['lifecycle' => LeadApiLifecycleStatus::DEPROVISION_INITIATED]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @deprecated use markApiDeprovisionInitiated / markApiDeprovisionCompleted */
    public function markApiDeprovisioned(int $leadId): void
    {
        $this->markApiDeprovisionCompleted($leadId);
    }

    /** @return list<array<string, mixed>> */
    public function findDemosOlderThanDays(int $days): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM dom_mkt_leads
             WHERE deleted = 0
               AND estado = :estado
               AND api_tenant_public_id IS NOT NULL
               AND api_provisioned_at IS NOT NULL
               AND api_provisioned_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->bindValue('estado', 'demo_enviada');
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function findDemosExpired(): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM dom_mkt_leads
             WHERE deleted = 0
               AND estado = :estado
               AND api_tenant_public_id IS NOT NULL
               AND (
                 (demo_expires_at IS NOT NULL AND demo_expires_at < NOW())
                 OR (demo_expires_at IS NULL AND api_provisioned_at IS NOT NULL AND api_provisioned_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
               )"
        );
        $stmt->execute(['estado' => 'demo_enviada']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findDemoPackageBySlug(string $slug): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT * FROM dom_mkt_paquetes WHERE slug = :slug AND deleted = 0 AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
