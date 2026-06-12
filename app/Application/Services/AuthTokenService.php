<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\AuthToken;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;

/*
|--------------------------------------------------------------------------
| AuthTokenService — Emisión de tokens de un solo uso
|--------------------------------------------------------------------------
| Centraliza la política del spec §8: 32 bytes aleatorios, solo el hash
| sha256 en BD, invalidación de previos y throttle por usuario+tipo.
*/

final class AuthTokenService
{
    public function __construct(
        private readonly AuthTokenRepositoryInterface $tokenRepo,
        private readonly int $maxPorHora = 3
    ) {
    }

    /**
     * Emite un token nuevo y devuelve el valor EN CLARO para la URL.
     * Devuelve null si el usuario excedió el máximo de emisiones por hora
     * (el caller responde genérico, sin enviar nada).
     */
    public function emitir(int $usuarioId, string $tipo, int $ttlMinutos): ?string
    {
        if ($this->tokenRepo->contarRecientes($usuarioId, $tipo, 60) >= $this->maxPorHora) {
            return null;
        }

        $this->tokenRepo->invalidarDeUsuario($usuarioId, $tipo);

        $token = bin2hex(random_bytes(32));

        $this->tokenRepo->guardar(new AuthToken(
            usuarioId: $usuarioId,
            tipo:      $tipo,
            tokenHash: hash('sha256', $token),
            expiraEn:  date('Y-m-d H:i:s', time() + $ttlMinutos * 60)
        ));

        return $token;
    }
}
