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
| ReenviarVerificacionUseCase — Reenvío anti-enumeración
|--------------------------------------------------------------------------
| Solo actúa para cuentas pendientes (inactivas y sin verificar). Para
| cualquier otro caso retorna en silencio: la respuesta observable es
| idéntica exista o no la cuenta (spec §8.2). El throttle vive en
| AuthTokenService (null => silencio).
*/

final class ReenviarVerificacionUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo,
        private readonly AuthTokenService           $tokens,
        private readonly CorreoAuthService          $correo,
        private readonly int                        $verificacionTtlMin
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
        if ($usuario === null
            || $usuario->id() === null
            || $usuario->activo()
            || $usuario->emailVerificadoEn() !== null) {
            return;
        }

        $token = $this->tokens->emitir($usuario->id(), AuthToken::TIPO_VERIFICACION, $this->verificacionTtlMin);
        if ($token !== null) {
            $this->correo->enviarVerificacion($usuario, $token);
        }
    }
}
