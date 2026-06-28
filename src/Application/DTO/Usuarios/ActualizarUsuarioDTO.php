<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\DTO\Usuarios;

final class ActualizarUsuarioDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly string $email,
        public readonly array  $rolIds = [],
        public readonly bool   $activo = true,
        public readonly string $password = ''
    ) {}
}
