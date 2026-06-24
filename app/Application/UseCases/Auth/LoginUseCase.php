<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\Services\AuthService;
use App\Application\Services\LoginRateLimitService;
use App\Application\Validators\Auth\LoginValidator;
use App\Domain\Exceptions\AuthException;

/*
|--------------------------------------------------------------------------
| LoginUseCase — Caso de uso: iniciar sesión
|--------------------------------------------------------------------------
*/

final class LoginUseCase
{
    public function __construct(
        private readonly AuthService           $authService,
        private readonly LoginValidator        $validator,
        private readonly LoginRateLimitService $rateLimit
    ) {}

    public function execute(LoginDTO $dto): void
    {
        $this->validator->validate([
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);

        $emailNormalizado = strtolower(trim($dto->email));
        $ip               = $dto->clientIp;

        $this->rateLimit->asegurarPermitido($ip, $emailNormalizado);

        try {
            $usuario = $this->authService->autenticar($dto->email, $dto->password);
        } catch (AuthException $e) {
            $this->rateLimit->registrarFallo($ip, $emailNormalizado);
            throw $e;
        }

        $this->rateLimit->limpiarTrasExito($ip, $emailNormalizado);
        $this->authService->iniciarSesion($usuario);

        if ($dto->recordar) {
            \App\Kernel\Security\Session::aplicarDuracionRecordar();
        }
    }
}
