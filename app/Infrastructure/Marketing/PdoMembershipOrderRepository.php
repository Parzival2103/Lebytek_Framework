<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoMembershipOrderRepository implements MembershipOrderRepositoryInterface
{
    public function create(array $data): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_ordenes
             (public_id, paquete_id, paquete_slug, ciclo, precio_snapshot, mensajes_mes_limite_snapshot,
              nombre, email, telefono, empresa, direccion, rfc, lead_id, api_tenant_public_id, status)
             VALUES
             (:public_id, :paquete_id, :paquete_slug, :ciclo, :precio_snapshot, :mensajes_mes_limite_snapshot,
              :nombre, :email, :telefono, :empresa, :direccion, :rfc, :lead_id, :api_tenant_public_id, :status)'
        );
        $stmt->execute([
            'public_id' => $data['public_id'],
            'paquete_id' => $data['paquete_id'],
            'paquete_slug' => $data['paquete_slug'],
            'ciclo' => $data['ciclo'],
            'precio_snapshot' => $data['precio_snapshot'],
            'mensajes_mes_limite_snapshot' => $data['mensajes_mes_limite_snapshot'],
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'empresa' => $data['empresa'],
            'direccion' => $data['direccion'],
            'rfc' => $data['rfc'],
            'lead_id' => $data['lead_id'],
            'api_tenant_public_id' => $data['api_tenant_public_id'],
            'status' => $data['status'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_ordenes WHERE id = :id AND deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_ordenes WHERE public_id = :pid AND deleted = 0 LIMIT 1');
        $stmt->execute(['pid' => $publicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markTransferNotified(int $orderId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_ordenes
             SET transfer_notified_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted = 0 AND transfer_notified_at IS NULL'
        );
        $stmt->execute(['id' => $orderId]);
    }

    public function setApiActivationError(int $orderId, string $error): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_ordenes
             SET api_activation_error = :error, updated_at = NOW()
             WHERE id = :id AND deleted = 0'
        );
        $stmt->execute(['id' => $orderId, 'error' => $error]);
    }

    public function markPaid(int $orderId, int $authorizedBy): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_ordenes
             SET status = \'paid\', authorized_at = NOW(), authorized_by = :by,
                 api_activation_error = NULL, updated_at = NOW()
             WHERE id = :id AND deleted = 0'
        );
        $stmt->execute(['id' => $orderId, 'by' => $authorizedBy]);
    }

    public function updateTenantPublicId(int $orderId, string $tenantPublicId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE dom_mkt_ordenes
             SET api_tenant_public_id = :tid, updated_at = NOW()
             WHERE id = :id AND deleted = 0'
        );
        $stmt->execute(['id' => $orderId, 'tid' => $tenantPublicId]);
    }
}
