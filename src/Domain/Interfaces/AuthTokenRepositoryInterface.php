<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Domain\Entities\AuthToken;

/*
|--------------------------------------------------------------------------
| AuthTokenRepositoryInterface — Contrato de persistencia de AuthToken
|--------------------------------------------------------------------------
*/

interface AuthTokenRepositoryInterface
{
    public function guardar(AuthToken $token): int;

    /** Busca un token no usado y no expirado por su hash sha256 y tipo. */
    public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken;

    public function marcarUsado(int $id): void;

    /** Invalida (marca usados) todos los tokens vigentes del usuario para ese tipo. */
    public function invalidarDeUsuario(int $usuarioId, string $tipo): void;

    /** Cuenta tokens emitidos al usuario para ese tipo en los últimos N minutos. */
    public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int;
}
