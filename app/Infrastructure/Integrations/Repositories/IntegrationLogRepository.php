<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Repositories;

use App\Domain\Integrations\IntegrationLogRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;
use App\Kernel\Database\Connection;

/*
|--------------------------------------------------------------------------
| IntegrationLogRepository — persiste cada intento de envío en int_logs.
|--------------------------------------------------------------------------
| Las tablas int_* NO se exponen por el CRUD Engine; las gestiona este repo.
*/
final class IntegrationLogRepository extends BaseRepository implements IntegrationLogRepositoryInterface
{
    protected string $table = 'int_logs';

    public function record(
        string $channel,
        string $driver,
        string $recipientMasked,
        string $status,
        ?string $providerMessageId,
        ?string $error,
        array $meta
    ): void {
        $this->execute(
            "INSERT INTO int_logs
                (channel, driver, recipient_masked, status, provider_message_id, error, meta, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                $channel,
                $driver,
                $recipientMasked,
                $status,
                $providerMessageId,
                $error,
                $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                isset($meta['user_id']) ? (int) $meta['user_id'] : null,
            ]
        );
    }

    public function countRecent(string $channel, int $windowSeconds): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM int_logs
             WHERE channel = ? AND status = 'sent'
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$channel, $windowSeconds]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function recent(int $limit = 50, ?string $channel = null): array
    {
        $limit = max(1, min(200, $limit));
        if ($channel !== null) {
            $stmt = Connection::getInstance()->prepare(
                'SELECT channel, driver, recipient_masked, status, provider_message_id, created_at
                 FROM int_logs WHERE channel = ? ORDER BY id DESC LIMIT ' . $limit
            );
            $stmt->execute([$channel]);
        } else {
            $stmt = Connection::getInstance()->prepare(
                'SELECT channel, driver, recipient_masked, status, provider_message_id, created_at
                 FROM int_logs ORDER BY id DESC LIMIT ' . $limit
            );
            $stmt->execute();
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
