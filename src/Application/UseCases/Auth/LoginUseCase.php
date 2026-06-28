<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Auth;

use Lebytek\Framework\Application\DTO\Auth\LoginDTO;
use Lebytek\Framework\Application\Services\AuthService;
use Lebytek\Framework\Application\Services\LoginRateLimitService;
use Lebytek\Framework\Application\Validators\Auth\LoginValidator;
use Lebytek\Framework\Domain\Exceptions\AuthException;

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
            \Lebytek\Framework\Kernel\Security\Session::aplicarDuracionRecordar();
        }
    }
}
