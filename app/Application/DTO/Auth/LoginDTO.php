<?php

declare(strict_types=1);

namespace App\Application\DTO\Auth;

/*
|--------------------------------------------------------------------------
| LoginDTO — Datos de entrada para el caso de uso de login
|--------------------------------------------------------------------------
*/

final class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool   $recordar = false
    ) {}
}
