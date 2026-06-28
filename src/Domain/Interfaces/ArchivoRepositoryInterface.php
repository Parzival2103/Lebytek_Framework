<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Domain\Entities\Archivo;

/*
|--------------------------------------------------------------------------
| ArchivoRepositoryInterface — Contrato de persistencia del ledger de archivos
|--------------------------------------------------------------------------
| El dominio define qué necesita; la infraestructura implementa cómo.
*/

interface ArchivoRepositoryInterface
{
    /** Inserta el archivo y devuelve su id. */
    public function guardar(Archivo $archivo): int;

    /** Devuelve el archivo por id, incluyendo borrados (validación de pertenencia). */
    public function buscarPorId(int $id): ?Archivo;

    /**
     * Historial vigente de una colección: solo no borrados, más reciente primero.
     *
     * @return Archivo[]
     */
    public function listarPorEntidad(string $tipo, int $id, string $coleccion): array;

    /** El archivo con es_actual=1 no borrado de la colección, si existe. */
    public function buscarActual(string $tipo, int $id, string $coleccion): ?Archivo;

    /** Transaccional: pone es_actual=0 a toda la colección y =1 al elegido. */
    public function marcarActual(int $archivoId, string $tipo, int $entidadId, string $coleccion): void;

    /** Soft delete: setea deleted_at=NOW() y es_actual=0. Devuelve filas afectadas. */
    public function softDelete(int $archivoId): int;
}
