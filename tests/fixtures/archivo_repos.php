<?php

declare(strict_types=1);

use App\Domain\Entities\Archivo;
use App\Domain\Interfaces\ArchivoRepositoryInterface;

if (!class_exists('FakeArchivoRepository')) {
    /**
     * Repositorio del ledger de archivos en memoria, para tests sin BD.
     * Replica fielmente las semánticas del contrato (es la pieza que usan
     * todos los tests de use cases de archivos/avatares).
     */
    class FakeArchivoRepository implements ArchivoRepositoryInterface
    {
        /** @var array<int, Archivo> id => Archivo */
        public array $archivos = [];
        private int $nextId = 1;

        public function guardar(Archivo $archivo): int
        {
            $id  = $this->nextId++;
            $row = $archivo->toArray();
            $this->archivos[$id] = Archivo::desdeFila([
                'id'              => $id,
                'entidad_tipo'    => $row['entidadTipo'],
                'entidad_id'      => $row['entidadId'],
                'coleccion'       => $row['coleccion'],
                'ruta'            => $row['ruta'],
                'thumbnail_ruta'  => $row['thumbnailRuta'],
                'nombre_original' => $row['nombreOriginal'],
                'mime'            => $row['mime'],
                'extension'       => $row['extension'],
                'tamano_bytes'    => $row['tamanoBytes'],
                'disco'           => $row['disco'],
                'es_actual'       => $row['esActual'],
                'creado_por'      => $row['creadoPor'],
                'created_at'      => $row['createdAt'] ?? date('Y-m-d H:i:s'),
                'deleted_at'      => $row['deletedAt'],
            ]);

            return $id;
        }

        public function buscarPorId(int $id): ?Archivo
        {
            return $this->archivos[$id] ?? null;
        }

        public function listarPorEntidad(string $tipo, int $id, string $coleccion): array
        {
            $result = [];
            foreach ($this->archivos as $archivo) {
                if (
                    $archivo->entidadTipo() === $tipo
                    && $archivo->entidadId() === $id
                    && $archivo->coleccion() === $coleccion
                    && $archivo->deletedAt() === null
                ) {
                    $result[] = $archivo;
                }
            }

            // Más reciente primero (id descendente, equivalente a created_at DESC).
            usort($result, fn(Archivo $a, Archivo $b) => $b->id() <=> $a->id());

            return $result;
        }

        public function buscarActual(string $tipo, int $id, string $coleccion): ?Archivo
        {
            foreach ($this->listarPorEntidad($tipo, $id, $coleccion) as $archivo) {
                if ($archivo->esActual()) {
                    return $archivo;
                }
            }

            return null;
        }

        public function marcarActual(int $archivoId, string $tipo, int $entidadId, string $coleccion): void
        {
            foreach ($this->archivos as $id => $archivo) {
                if (
                    $archivo->entidadTipo() !== $tipo
                    || $archivo->entidadId() !== $entidadId
                    || $archivo->coleccion() !== $coleccion
                ) {
                    continue;
                }
                $row = $archivo->toArray();
                $this->archivos[$id] = Archivo::desdeFila([
                    'id'              => $id,
                    'entidad_tipo'    => $row['entidadTipo'],
                    'entidad_id'      => $row['entidadId'],
                    'coleccion'       => $row['coleccion'],
                    'ruta'            => $row['ruta'],
                    'thumbnail_ruta'  => $row['thumbnailRuta'],
                    'nombre_original' => $row['nombreOriginal'],
                    'mime'            => $row['mime'],
                    'extension'       => $row['extension'],
                    'tamano_bytes'    => $row['tamanoBytes'],
                    'disco'           => $row['disco'],
                    'es_actual'       => $id === $archivoId,
                    'creado_por'      => $row['creadoPor'],
                    'created_at'      => $row['createdAt'],
                    'deleted_at'      => $row['deletedAt'],
                ]);
            }
        }

        public function softDelete(int $archivoId): void
        {
            $archivo = $this->archivos[$archivoId] ?? null;
            if ($archivo === null) {
                return;
            }
            $this->archivos[$archivoId] = $archivo->marcarBorrado();
        }
    }
}
