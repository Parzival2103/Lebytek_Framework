<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Repositories;

use Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;

final class ConfiguracionRepository extends BaseRepository implements ConfiguracionRepositoryInterface
{
    protected string $table = 'cfg_configuraciones';

    public function get(string $clave, mixed $default = null): mixed
    {
        $row = $this->queryOne(
            "SELECT valor FROM cfg_configuraciones WHERE clave = ? LIMIT 1",
            [$clave]
        );
        return $row ? $row['valor'] : $default;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->execute(
            "INSERT INTO cfg_configuraciones (clave, valor, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()",
            [$clave, (string) $valor]
        );
    }

    public function all(): array
    {
        $rows   = $this->query("SELECT clave, valor FROM cfg_configuraciones");
        $result = [];
        foreach ($rows as $row) {
            $result[$row['clave']] = $row['valor'];
        }
        return $result;
    }

    public function setMultiple(array $datos): void
    {
        $this->beginTransaction();
        try {
            foreach ($datos as $clave => $valor) {
                $this->set((string) $clave, $valor);
            }
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
