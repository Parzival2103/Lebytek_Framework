<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Auth;

use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\AuthTokenRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;

/*
|--------------------------------------------------------------------------
| VerificarCorreoUseCase — Consume el token y activa la cuenta
|--------------------------------------------------------------------------
| No inicia sesión (spec §8.5): el controller redirige a /login.
*/

final class VerificarCorreoUseCase
{
    private const MENSAJE_INVALIDO = 'El enlace de verificación no es válido o ya expiró.';

    public function __construct(
        private readonly AuthTokenRepositoryInterface $tokenRepo,
        private readonly UsuarioRepositoryInterface   $usuarioRepo
    ) {
    }

    public function execute(string $tokenClaro): void
    {
        $token = $this->tokenRepo->buscarVigentePorHash(
            hash('sha256', $tokenClaro),
            AuthToken::TIPO_VERIFICACION
        );

        if ($token === null || $token->id() === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $usuario = $this->usuarioRepo->findById($token->usuarioId());
        if ($usuario === null) {
            throw new ValidationException(self::MENSAJE_INVALIDO);
        }

        $this->tokenRepo->marcarUsado($token->id());
        $this->usuarioRepo->marcarEmailVerificado($token->usuarioId());
    }
}
