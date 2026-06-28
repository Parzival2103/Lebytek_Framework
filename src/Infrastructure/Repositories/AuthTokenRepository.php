<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Repositories;

use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Interfaces\AuthTokenRepositoryInterface;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;

final class AuthTokenRepository extends BaseRepository implements AuthTokenRepositoryInterface
{
    protected string $table = 'auth_tokens';

    public function guardar(AuthToken $token): int
    {
        return $this->insert(
            "INSERT INTO auth_tokens (usuario_id, tipo, token_hash, expira_en, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                $token->usuarioId(),
                $token->tipo(),
                $token->tokenHash(),
                $token->expiraEn(),
            ]
        );
    }

    public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken
    {
        $row = $this->queryOne(
            "SELECT * FROM auth_tokens
             WHERE token_hash = ? AND tipo = ? AND usado_en IS NULL AND expira_en > NOW()
             LIMIT 1",
            [$hash, $tipo]
        );
        return $row ? AuthToken::desdeFila($row) : null;
    }

    public function marcarUsado(int $id): void
    {
        $this->execute(
            "UPDATE auth_tokens SET usado_en = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function invalidarDeUsuario(int $usuarioId, string $tipo): void
    {
        $this->execute(
            "UPDATE auth_tokens SET usado_en = NOW()
             WHERE usuario_id = ? AND tipo = ? AND usado_en IS NULL",
            [$usuarioId, $tipo]
        );
    }

    public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM auth_tokens
             WHERE usuario_id = ? AND tipo = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$usuarioId, $tipo, $minutos]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
