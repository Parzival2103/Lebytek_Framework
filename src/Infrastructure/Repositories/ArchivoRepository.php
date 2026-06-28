<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Archivo;
use App\Domain\Interfaces\ArchivoRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

/*
|--------------------------------------------------------------------------
| ArchivoRepository — Persistencia PDO del ledger de archivos
|--------------------------------------------------------------------------
| Implementa el contrato del dominio sobre core_archivos con prepared
| statements; marcarActual es transaccional (un solo actual por colección).
*/

final class ArchivoRepository extends BaseRepository implements ArchivoRepositoryInterface
{
    protected string $table = 'core_archivos';

    public function guardar(Archivo $archivo): int
    {
        return $this->insert(
            "INSERT INTO {$this->table}
                (entidad_tipo, entidad_id, coleccion, ruta, thumbnail_ruta,
                 nombre_original, mime, extension, tamano_bytes, disco,
                 es_actual, creado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $archivo->entidadTipo(),
                $archivo->entidadId(),
                $archivo->coleccion(),
                $archivo->ruta(),
                $archivo->thumbnailRuta(),
                $archivo->nombreOriginal(),
                $archivo->mime(),
                $archivo->extension(),
                $archivo->tamanoBytes(),
                $archivo->disco(),
                $archivo->esActual() ? 1 : 0,
                $archivo->creadoPor(),
            ]
        );
    }

    public function buscarPorId(int $id): ?Archivo
    {
        $row = $this->findRowById($id);

        return $row !== null ? Archivo::desdeFila($row) : null;
    }

    public function listarPorEntidad(string $tipo, int $id, string $coleccion): array
    {
        $rows = $this->query(
            "SELECT * FROM {$this->table}
             WHERE entidad_tipo = ? AND entidad_id = ? AND coleccion = ?
               AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC",
            [$tipo, $id, $coleccion]
        );

        return array_map(fn(array $row) => Archivo::desdeFila($row), $rows);
    }

    public function buscarActual(string $tipo, int $id, string $coleccion): ?Archivo
    {
        $row = $this->queryOne(
            "SELECT * FROM {$this->table}
             WHERE entidad_tipo = ? AND entidad_id = ? AND coleccion = ?
               AND es_actual = 1 AND deleted_at IS NULL
             LIMIT 1",
            [$tipo, $id, $coleccion]
        );

        return $row !== null ? Archivo::desdeFila($row) : null;
    }

    public function marcarActual(int $archivoId, string $tipo, int $entidadId, string $coleccion): void
    {
        $this->beginTransaction();

        try {
            $this->execute(
                "UPDATE {$this->table} SET es_actual = 0
                 WHERE entidad_tipo = ? AND entidad_id = ? AND coleccion = ?",
                [$tipo, $entidadId, $coleccion]
            );
            $this->execute(
                "UPDATE {$this->table} SET es_actual = 1 WHERE id = ?",
                [$archivoId]
            );
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function softDelete(int $archivoId): int
    {
        return $this->execute(
            "UPDATE {$this->table} SET deleted_at = NOW(), es_actual = 0 WHERE id = ?",
            [$archivoId]
        );
    }
}
