<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Auth;

use Lebytek\Framework\Application\Services\AuthService;

final class LogoutUseCase
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function execute(): void
    {
        $this->authService->cerrarSesion();
    }
}
