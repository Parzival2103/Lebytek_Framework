<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

interface AuditoriaLoggerInterface
{
    public function auditarCreacion(string $tabla, int $registroId, string $ip = ''): void;

    public function auditarActualizacion(string $tabla, int $registroId, string $ip = ''): void;

    public function auditarEliminacion(string $tabla, int $registroId, string $ip = ''): void;

    public function auditarLogin(int $usuarioId, string $ip = ''): void;

    public function auditarLogout(int $usuarioId, string $ip = ''): void;
}
