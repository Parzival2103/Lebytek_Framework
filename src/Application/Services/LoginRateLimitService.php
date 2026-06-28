<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Exceptions\AuthException;
use Lebytek\Framework\Domain\Interfaces\LoginIntentoRepositoryInterface;
use Lebytek\Framework\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| LoginRateLimitService — Política de límite de intentos de login
|--------------------------------------------------------------------------
| Contadores duales IP + email; bloqueo temporal sin lockout de cuenta.
| Desactivable con LOGIN_RATE_LIMIT_ENABLED=false.
*/

final class LoginRateLimitService
{
    private const MENSAJE_BLOQUEO = 'Credenciales incorrectas.';

    public function __construct(
        private readonly LoginIntentoRepositoryInterface $repo,
        private readonly int $maxIntentos,
        private readonly int $ventanaMin,
        private readonly bool $habilitado = true
    ) {
    }

    public function asegurarPermitido(string $ip, string $emailNormalizado): void
    {
        if (!$this->habilitado) {
            return;
        }

        if ($this->estaBloqueado('ip', $ip) || $this->estaBloqueado('email', $emailNormalizado)) {
            AppLogger::warning('Login bloqueado por rate limit', [
                'ip' => $ip,
            ]);
            throw new AuthException(self::MENSAJE_BLOQUEO);
        }
    }

    public function registrarFallo(string $ip, string $emailNormalizado): void
    {
        if (!$this->habilitado) {
            return;
        }

        $this->repo->registrarFallo($ip, $emailNormalizado);
        $this->repo->purgarAntiguos($this->ventanaMin);

        if ($this->estaBloqueado('ip', $ip) || $this->estaBloqueado('email', $emailNormalizado)) {
            AppLogger::warning('Login alcanzó umbral de intentos fallidos', [
                'ip' => $ip,
            ]);
        }
    }

    public function limpiarTrasExito(string $ip, string $emailNormalizado): void
    {
        if (!$this->habilitado) {
            return;
        }

        $this->repo->limpiarPara($ip, $emailNormalizado);
    }

    private function estaBloqueado(string $dimension, string $clave): bool
    {
        return $this->repo->contarFallosRecientes($dimension, $clave, $this->ventanaMin) >= $this->maxIntentos;
    }
}
