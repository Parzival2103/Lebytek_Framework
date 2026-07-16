<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use Lebytek\Framework\Kernel\Database\Connection;

final class PdoPlantillaRepository implements PlantillaRepositoryInterface
{
    public function findActiveByClave(string $clave): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare(
            'SELECT id, clave, asunto, cuerpo, activo
             FROM dom_mkt_plantillas
             WHERE clave = :clave AND activo = 1 AND deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['clave' => $clave]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
