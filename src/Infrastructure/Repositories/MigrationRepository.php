<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Repositories;

use Lebytek\Framework\Domain\Interfaces\MigrationRepositoryInterface;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;

final class MigrationRepository extends BaseRepository implements MigrationRepositoryInterface
{
    protected string $table = 'cfg_migraciones';

    public function aplicadas(): array
    {
        if (!$this->existeTabla('cfg_migraciones')) {
            return [];
        }
        $rows = $this->query("SELECT archivo, checksum FROM cfg_migraciones");
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['archivo']] = (string) $row['checksum'];
        }
        return $out;
    }

    public function registrar(string $modulo, string $archivo, string $checksum): void
    {
        $this->execute(
            "INSERT INTO cfg_migraciones (modulo, archivo, checksum)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), modulo = VALUES(modulo)",
            [$modulo, $archivo, $checksum]
        );
    }

    public function existeTabla(string $nombre): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?",
            [$nombre]
        );
        return $row !== null && (int) $row['n'] > 0;
    }
}
