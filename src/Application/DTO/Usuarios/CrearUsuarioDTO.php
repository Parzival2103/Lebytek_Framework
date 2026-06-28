<?php

declare(strict_types=1);

namespace App\Application\DTO\Usuarios;

final class CrearUsuarioDTO
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly string $email,
        public readonly string $password,
        public readonly array  $rolIds  = [],
        public readonly bool   $activo  = true
    ) {}
}
