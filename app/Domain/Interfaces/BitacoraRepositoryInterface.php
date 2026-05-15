<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

interface BitacoraRepositoryInterface
{
    public function registrar(?int $usuarioId, string $accion, string $tabla = '', ?int $registroId = null, string $detalle = '', string $ip = ''): void;

    public function recientes(int $limit = 50): array;
}
