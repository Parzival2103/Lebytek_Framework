<?php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\ReporteRepositoryInterface;
use App\Domain\Reporte\ReporteGuardado;
use App\Kernel\BaseClasses\BaseRepository;

/**
 * Persistencia de rep_reportes. Scope owner: cada quien ve los suyos; compartido=1
 * los publica. Un reporte ajeno no compartido no se devuelve (el controlador lo
 * traduce a 404). Borrado lógico vía columna `deleted`.
 */
final class PdoReporteRepository extends BaseRepository implements ReporteRepositoryInterface
{
    protected string $table = 'rep_reportes';

    public function findVisible(int $id, int $userId): ?ReporteGuardado
    {
        $row = $this->queryOne(
            "SELECT * FROM rep_reportes
             WHERE id = ? AND deleted = 0 AND (created_by = ? OR compartido = 1)
             LIMIT 1",
            [$id, $userId]
        );

        return $row === null ? null : ReporteGuardado::fromRow($row);
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->query(
            "SELECT id, nombre, fuente_key, modo, template_key, compartido, created_by, updated_at, created_at
             FROM rep_reportes
             WHERE deleted = 0 AND (created_by = ? OR compartido = 1)
             ORDER BY updated_at DESC, created_at DESC",
            [$userId]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->insert(
            "INSERT INTO rep_reportes
                (nombre, fuente_key, modo, columnas, tratamientos, filtros, periodo, opciones,
                 template_key, compartido, deleted, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)",
            [
                $data['nombre'], $data['fuente_key'], $data['modo'], $data['columnas'],
                $data['tratamientos'], $data['filtros'], $data['periodo'], $data['opciones'],
                $data['template_key'], $data['compartido'], $data['created_by'],
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, int $userId, array $data): void
    {
        $this->execute(
            "UPDATE rep_reportes SET
                nombre = ?, modo = ?, columnas = ?, tratamientos = ?, filtros = ?,
                periodo = ?, opciones = ?, template_key = ?, compartido = ?,
                updated_at = NOW(), updated_by = ?
             WHERE id = ? AND created_by = ? AND deleted = 0",
            [
                $data['nombre'], $data['modo'], $data['columnas'], $data['tratamientos'],
                $data['filtros'], $data['periodo'], $data['opciones'], $data['template_key'],
                $data['compartido'], $userId, $id, $userId,
            ]
        );
    }

    public function delete(int $id, int $userId): void
    {
        $this->execute(
            "UPDATE rep_reportes SET deleted = 1, deleted_at = NOW(), deleted_by = ?
             WHERE id = ? AND created_by = ? AND deleted = 0",
            [$userId, $id, $userId]
        );
    }
}
