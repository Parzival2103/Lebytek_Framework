<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;
use App\Kernel\Database\Connection;

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
            'SELECT nombre, precio_mensual, precio_anual, features, destacado, badge
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
}
