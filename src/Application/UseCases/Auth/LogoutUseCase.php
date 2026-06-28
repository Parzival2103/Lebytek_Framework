<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\Services\AuthService;

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
