<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoVariantWeightRepository implements VariantWeightRepositoryInterface
{
    /** @return array<string, float> */
    public function all(): array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->query('SELECT slug, weight FROM dom_mkt_variant_weights');
        $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['slug']] = (float) $row['weight'];
        }

        return $out;
    }

    public function get(string $slug): ?float
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare('SELECT weight FROM dom_mkt_variant_weights WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (float) $value;
    }

    public function upsert(string $slug, float $weight): void
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO dom_mkt_variant_weights (slug, weight)
             VALUES (:slug, :weight)
             ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = NOW()'
        );
        $stmt->execute(['slug' => $slug, 'weight' => $weight]);
    }

    /**
     * INSERT-only para slugs sin fila existente. **Nunca** sobrescribe pesos ya
     * editados por ops (Anti-deuda §W) — `INSERT IGNORE` descarta silenciosamente
     * los slugs cuya `slug` (PK) ya existe.
     *
     * @param array<string, float> $defaults
     */
    public function seedMissing(array $defaults): void
    {
        if ($defaults === []) {
            return;
        }

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO dom_mkt_variant_weights (slug, weight) VALUES (:slug, :weight)'
        );
        foreach ($defaults as $slug => $weight) {
            $stmt->execute(['slug' => (string) $slug, 'weight' => $weight]);
        }
    }
}
