<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\AuthService;
use App\Application\Validators\Auth\LoginValidator;
use App\Domain\Exceptions\AuthException;
use App\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| LoginUseCase — Caso de uso: iniciar sesión
|--------------------------------------------------------------------------
*/

final class LoginUseCase
{
    public function __construct(
        private readonly AuthService    $authService,
        private readonly LoginValidator $validator
    ) {}

    public function execute(LoginDTO $dto): void
    {
        $this->validator->validate([
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        $usuario = $this->authService->autenticar($dto->email, $dto->password);
        $this->authService->iniciarSesion($usuario);
    }
}
