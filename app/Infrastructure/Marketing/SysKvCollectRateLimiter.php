<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\CollectRateLimiterInterface;
use Lebytek\Framework\Kernel\Database\Connection;

/**
 * Rate limiter del colector `/marketing/collect` respaldado por `sys_kv`
 * (Anti-deuda §L). **No** usa `Session` PHP: los beacons a menudo viajan sin
 * cookie de sesión.
 *
 * Protocolo: valor JSON `{"count":int,"window_start":unix}` por clave
 * (`land_collect:{visitor_id}`, ≤100 chars). Ventana deslizante simple:
 * READ → decide allow/deny con el conteo actual → UPSERT con el conteo nuevo.
 * Se acepta un leve overcount por condiciones de carrera (no se usa locking
 * ni columna TTL adicional).
 */
final class SysKvCollectRateLimiter implements CollectRateLimiterInterface
{
    public function __construct(
        private readonly int $maxPerWindow = 120,
        private readonly int $windowSeconds = 3600,
    ) {}

    public function allow(string $key): bool
    {
        $pdo = Connection::getInstance();
        $now = time();

        $stmt = $pdo->prepare('SELECT valor FROM sys_kv WHERE clave = :clave LIMIT 1');
        $stmt->execute(['clave' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $count = 0;
        $windowStart = $now;

        if (is_array($row) && is_string($row['valor'] ?? null)) {
            $decoded = json_decode($row['valor'], true);
            if (is_array($decoded)) {
                $windowStart = (int) ($decoded['window_start'] ?? $now);
                $count = (int) ($decoded['count'] ?? 0);
            }
        }

        if ($now - $windowStart >= $this->windowSeconds) {
            // Ventana expirada: reinicia el conteo.
            $windowStart = $now;
            $count = 0;
        }

        $allowed = $count < $this->maxPerWindow;
        $count++;

        $value = json_encode(['count' => $count, 'window_start' => $windowStart], JSON_THROW_ON_ERROR);

        $upsert = $pdo->prepare(
            'INSERT INTO sys_kv (clave, valor, updated_at)
             VALUES (:clave, :valor, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()'
        );
        $upsert->execute(['clave' => $key, 'valor' => $value]);

        return $allowed;
    }
}
