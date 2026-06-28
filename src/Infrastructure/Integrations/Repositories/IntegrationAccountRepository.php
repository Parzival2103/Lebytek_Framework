<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\Repositories;

use Lebytek\Framework\Domain\Integrations\IntegrationAccount;
use Lebytek\Framework\Domain\Integrations\IntegrationAccountRepositoryInterface;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\Security\Crypto;

final class IntegrationAccountRepository extends BaseRepository implements IntegrationAccountRepositoryInterface
{
    protected string $table = 'int_accounts';

    public function findDefault(string $provider): ?IntegrationAccount
    {
        return $this->one('SELECT * FROM int_accounts WHERE provider = ? AND is_default = 1 LIMIT 1', [$provider]);
    }

    public function findById(int $id): ?IntegrationAccount
    {
        return $this->one('SELECT * FROM int_accounts WHERE id = ? LIMIT 1', [$id]);
    }

    public function findByLead(int $leadId, string $provider): ?IntegrationAccount
    {
        return $this->one(
            'SELECT * FROM int_accounts WHERE lead_id = ? AND provider = ? ORDER BY id DESC LIMIT 1',
            [$leadId, $provider]
        );
    }

    public function save(IntegrationAccount $a): int
    {
        $pdo = Connection::getInstance();
        $tokenEnc = Crypto::encrypt($a->token);
        if ($a->id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE int_accounts SET provider=?, label=?, instance_id=?, token_encrypted=?, is_default=?, lead_id=?, status=?, provisioned_via=? WHERE id=?'
            );
            $stmt->execute([
                $a->provider,
                $a->label,
                $a->instanceId,
                $tokenEnc,
                $a->isDefault ? 1 : 0,
                $a->leadId,
                $a->status,
                $a->provisionedVia,
                $a->id,
            ]);
            return $a->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO int_accounts (provider, label, instance_id, token_encrypted, is_default, lead_id, status, provisioned_via) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $a->provider,
            $a->label,
            $a->instanceId,
            $tokenEnc,
            $a->isDefault ? 1 : 0,
            $a->leadId,
            $a->status,
            $a->provisionedVia,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function markDefault(int $id, string $provider): void
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE int_accounts SET is_default = 0 WHERE provider = ?')->execute([$provider]);
            $pdo->prepare('UPDATE int_accounts SET is_default = 1 WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int, mixed> $params */
    private function one(string $sql, array $params): ?IntegrationAccount
    {
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): IntegrationAccount
    {
        return new IntegrationAccount(
            (int) $row['id'],
            (string) $row['provider'],
            (string) $row['label'],
            (string) $row['instance_id'],
            Crypto::decrypt((string) $row['token_encrypted']),
            (bool) $row['is_default'],
            $row['lead_id'] !== null ? (int) $row['lead_id'] : null,
            (string) $row['status'],
            (string) $row['provisioned_via'],
        );
    }
}
