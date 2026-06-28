<?php

declare(strict_types=1);

namespace App\Application\UseCases\Auth;

use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Domain\Entities\AuthToken;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;

/*
|--------------------------------------------------------------------------
| SolicitarRecuperacionUseCase — Solicitud anti-enumeración
|--------------------------------------------------------------------------
| El resultado observable es idéntico exista o no el email (spec §8.2):
| siempre retorna void; solo envía correo si la cuenta existe y está
| activa. El throttle vive en AuthTokenService (null => silencio).
*/

final class SolicitarRecuperacionUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly int                        $recuperacionTtlMin
    ) {
    }

    public function execute(string $email): void
    {
        try {
            $emailVO = new Email($email);
        } catch (ValidationException) {
            return;
        }

        $usuario = $this->usuarioRepo->findByEmail($emailVO);
        if ($usuario === null || $usuario->id() === null || !$usuario->activo()) {
            return;
        }

        $token = $this->tokens->emitir($usuario->id(), AuthToken::TIPO_RECUPERACION, $this->recuperacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarRecuperacion($usuario, $token);
        }
    }
}
