<?php
declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Payments;

use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;
use PDOException;

final class PdoPaymentEventLogRepository implements PaymentEventLogRepositoryInterface
{
    public function hasProcessed(string $provider, string $eventId): bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM pay_events WHERE provider = :p AND event_id = :e LIMIT 1'
        );
        $stmt->execute(['p' => $provider, 'e' => $eventId]);
        return (bool) $stmt->fetchColumn();
    }

    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $pdo = Connection::getInstance();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pay_events (provider, event_id, order_ref, type, payload_hash, meta)
                 VALUES (:provider, :event_id, :order_ref, :type, :payload_hash, :meta)'
            );
            $stmt->execute([
                'provider' => $provider,
                'event_id' => $eventId,
                'order_ref' => $orderRef !== '' ? $orderRef : null,
                'type' => $type,
                'payload_hash' => $payloadHash,
                'meta' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR),
            ]);
            return true;
        } catch (PDOException $e) {
            // SQLSTATE 23000 = integrity constraint violation (UNIQUE)
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return false;
            }
            throw $e;
        }
    }

    public function releaseClaim(string $provider, string $eventId): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'DELETE FROM pay_events WHERE provider = :provider AND event_id = :event_id'
        );
        $stmt->execute(['provider' => $provider, 'event_id' => $eventId]);
    }
}
