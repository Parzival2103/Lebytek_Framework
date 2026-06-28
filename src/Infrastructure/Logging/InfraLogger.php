<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Logging;

use Lebytek\Framework\Domain\Interfaces\AuditoriaLoggerInterface;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

final class InfraLogger implements AuditoriaLoggerInterface
{
    public function __construct(
        private readonly BitacoraRepositoryInterface $bitacora,
        private readonly ?int $usuarioId = null
    ) {}

    public function auditarCreacion(string $tabla, int $registroId, string $ip = ''): void
    {
        $this->bitacora->registrar($this->usuarioId, 'crear', $tabla, $registroId, '', $ip);
    }

    public function auditarActualizacion(string $tabla, int $registroId, string $ip = ''): void
    {
        $this->bitacora->registrar($this->usuarioId, 'actualizar', $tabla, $registroId, '', $ip);
    }

    public function auditarEliminacion(string $tabla, int $registroId, string $ip = ''): void
    {
        $this->bitacora->registrar($this->usuarioId, 'eliminar', $tabla, $registroId, '', $ip);
    }

    public function auditarLogin(int $usuarioId, string $ip = ''): void
    {
        $this->bitacora->registrar($usuarioId, 'login', 'auth_usuarios', $usuarioId, '', $ip);
    }

    public function auditarLogout(int $usuarioId, string $ip = ''): void
    {
        $this->bitacora->registrar($usuarioId, 'logout', 'auth_usuarios', $usuarioId, '', $ip);
    }
}
