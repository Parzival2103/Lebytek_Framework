<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\DTO\Auth;

final class RegistroDTO
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly string $email,
        public readonly string $password,
        public readonly string $passwordConfirmacion
    ) {
    }
}
