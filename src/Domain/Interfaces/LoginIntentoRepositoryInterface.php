<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

/*
|--------------------------------------------------------------------------
| LoginIntentoRepositoryInterface — Persistencia de fallos de login
|--------------------------------------------------------------------------
| Contadores por dimensión (ip | email) para rate limiting temporal.
*/

interface LoginIntentoRepositoryInterface
{
    public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int;

    public function registrarFallo(string $ip, string $emailNormalizado): void;

    public function limpiarPara(string $ip, string $emailNormalizado): void;

    public function purgarAntiguos(int $ventanaMin): void;
}
