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
