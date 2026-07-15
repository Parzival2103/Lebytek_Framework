<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoMarketingContentRepository implements MarketingContentRepositoryInterface
{
    public function bloquesPorPagina(string $pagina): array
    {
        $pdo  = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT clave, contenido FROM dom_mkt_bloques
             WHERE pagina = :pagina AND activo = 1 AND deleted = 0 ORDER BY orden ASC'
        );
        $stmt->execute(['pagina' => $pagina]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $contenido = json_decode((string) ($row['contenido'] ?? '{}'), true);
            $out[(string) $row['clave']] = is_array($contenido) ? $contenido : [];
        }
        return $out;
    }

    public function paquetesActivos(): array
    {
        $pdo  = Connection::getInstance();
        $stmt = $pdo->query(
            'SELECT id, slug, nombre, precio_mensual, precio_anual, mensajes_mes_limite, features, destacado, badge
             FROM dom_mkt_paquetes WHERE activo = 1 AND deleted = 0 ORDER BY orden ASC'
        );
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $features = json_decode((string) ($row['features'] ?? '[]'), true);
            $row['features'] = is_array($features) ? $features : [];
            $out[] = $row;
        }
        return $out;
    }

    public function findPaqueteBySlug(string $slug, bool $requireActive = true): ?array
    {
        $pdo = Connection::getInstance();
        $sql = 'SELECT * FROM dom_mkt_paquetes WHERE slug = :slug AND deleted = 0';
        if ($requireActive) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return null;
        }
        $features = json_decode((string) ($row['features'] ?? '[]'), true);
        $row['features'] = is_array($features) ? $features : [];

        return $row;
    }
}
