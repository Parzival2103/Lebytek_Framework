<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class ModuleStateRepository extends BaseRepository implements ModuleStateRepositoryInterface
{
    protected string $table = 'cfg_modulos';

    public function instalados(): array
    {
        if (!$this->tablaExiste('cfg_modulos')) {
            return [];
        }
        $rows = $this->query("SELECT clave, version, activo FROM cfg_modulos");
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['clave']] = [
                'version' => (string) $row['version'],
                'activo'  => (bool) $row['activo'],
            ];
        }
        return $out;
    }

    private function tablaExiste(string $nombre): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?",
            [$nombre]
        );
        return $row !== null && (int) $row['n'] > 0;
    }

    public function registrar(string $clave, string $version, bool $activo): void
    {
        $this->execute(
            "INSERT INTO cfg_modulos (clave, version, activo)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version), activo = VALUES(activo)",
            [$clave, $version, $activo ? 1 : 0]
        );
    }
}
