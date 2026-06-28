<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class BitacoraRepository extends BaseRepository implements BitacoraRepositoryInterface
{
    protected string $table = 'log_bitacora';

    public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void
    {
        $this->execute(
            "INSERT INTO log_bitacora (usuario_id, accion, tabla, registro_id, detalle, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$usuarioId, $accion, $tabla, $registroId, $detalle, $ip]
        );
    }

    public function recientes(int $limit = 50): array
    {
        return $this->query(
            "SELECT b.*, u.nombre, u.apellido
             FROM log_bitacora b
             LEFT JOIN auth_usuarios u ON u.id = b.usuario_id
             ORDER BY b.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function porRegistro(string $tabla, int $registroId, int $limit = 50): array
    {
        return $this->query(
            "SELECT b.*, u.nombre, u.apellido
             FROM log_bitacora b
             LEFT JOIN auth_usuarios u ON u.id = b.usuario_id
             WHERE b.tabla = ? AND b.registro_id = ?
             ORDER BY b.created_at DESC
             LIMIT ?",
            [$tabla, $registroId, $limit]
        );
    }
}
