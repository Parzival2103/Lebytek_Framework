<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\DTO\Roles;

final class ActualizarRolDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $nombre,
        public readonly string $slug        = '',
        public readonly string $descripcion = '',
        public readonly bool   $activo      = true,
        public readonly array  $permisoIds  = []
    ) {}
}
