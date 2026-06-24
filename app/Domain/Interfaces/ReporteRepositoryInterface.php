<?php
declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Domain\Reporte\ReporteGuardado;

/**
 * Persistencia de reportes guardados (rep_reportes). El scope owner (propios +
 * compartidos) se resuelve aquí; un reporte ajeno no compartido no se devuelve.
 */
interface ReporteRepositoryInterface
{
    /** Reporte visible para el usuario (propio o compartido), o null si no aplica. */
    public function findVisible(int $id, int $userId): ?ReporteGuardado;

    /** @return list<array<string,mixed>> filas crudas para el índice (propios + compartidos). */
    public function listForUser(int $userId): array;

    /** @param array<string,mixed> $data columnas de rep_reportes (JSON ya serializado). */
    public function create(array $data): int;

    /** @param array<string,mixed> $data */
    public function update(int $id, int $userId, array $data): void;

    public function delete(int $id, int $userId): void;
}
