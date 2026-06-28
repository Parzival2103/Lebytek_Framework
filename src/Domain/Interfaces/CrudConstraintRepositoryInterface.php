<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

/**
 * Contrato mínimo para validar constraints de DB del CRUD Engine.
 * Implementado por GenericCrudRepository (Infraestructura). Existe como
 * interfaz para mantener CrudDbConstraintValidator unit-testable sin DB.
 */
interface CrudConstraintRepositoryInterface
{
    /**
     * ¿Existe OTRA fila no borrada con ese valor en la columna? (unique).
     * Excluye la fila $exceptId cuando se provee (ignore_self en update).
     */
    public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool;

    /**
     * ¿Existe al menos una fila con ese valor en la columna? (exists / FK).
     */
    public function existsForReference(string $table, string $column, mixed $value): bool;
}
