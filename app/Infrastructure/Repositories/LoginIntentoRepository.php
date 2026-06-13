<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\LoginIntentoRepositoryInterface;
use App\Kernel\BaseClasses\BaseRepository;

final class LoginIntentoRepository extends BaseRepository implements LoginIntentoRepositoryInterface
{
    protected string $table = 'auth_login_intentos';

    public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM auth_login_intentos
             WHERE dimension = ? AND clave = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$dimension, $clave, $ventanaMin]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function registrarFallo(string $ip, string $emailNormalizado): void
    {
        $this->execute(
            "INSERT INTO auth_login_intentos (dimension, clave, created_at) VALUES
             ('ip', ?, NOW()), ('email', ?, NOW())",
            [$ip, $emailNormalizado]
        );
    }

    public function limpiarPara(string $ip, string $emailNormalizado): void
    {
        $this->execute(
            "DELETE FROM auth_login_intentos
             WHERE (dimension = 'ip' AND clave = ?)
                OR (dimension = 'email' AND clave = ?)",
            [$ip, $emailNormalizado]
        );
    }

    public function purgarAntiguos(int $ventanaMin): void
    {
        $this->execute(
            "DELETE FROM auth_login_intentos
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$ventanaMin * 2]
        );
    }
}
