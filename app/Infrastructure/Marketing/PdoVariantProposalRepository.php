<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoVariantProposalRepository implements VariantProposalRepositoryInterface
{
    /** @param array<string, mixed> $payload */
    public function insertPending(array $payload): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO dom_mkt_variant_proposals (status, payload) VALUES ('pending', :payload)"
        );
        $stmt->execute(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);

        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function findPending(): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM dom_mkt_variant_proposals WHERE status = 'pending' ORDER BY created_at DESC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map($this->decodePayload(...), is_array($rows) ? $rows : []);
    }

    /** @return array<string, mixed>|null */
    public function findLatestPending(): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM dom_mkt_variant_proposals WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->decodePayload($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM dom_mkt_variant_proposals WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->decodePayload($row) : null;
    }

    public function acceptAtomically(int $id, int $userId, callable $applyWeights): bool
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "UPDATE dom_mkt_variant_proposals
                 SET status = 'accepted', resolved_at = NOW(), resolved_by = :user_id
                 WHERE id = :id AND status = 'pending'"
            );
            $stmt->execute(['user_id' => $userId, 'id' => $id]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();

                return false;
            }

            $applyWeights();
            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function markRejected(int $id, int $userId): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "UPDATE dom_mkt_variant_proposals
             SET status = 'rejected', resolved_at = NOW(), resolved_by = :user_id
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['user_id' => $userId, 'id' => $id]);

        return $stmt->rowCount();
    }

    public function supersedeAllPending(): int
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            "UPDATE dom_mkt_variant_proposals SET status = 'superseded', resolved_at = NOW() WHERE status = 'pending'"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodePayload(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }

        return $row;
    }
}
