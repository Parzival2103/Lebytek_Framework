<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Repositories;

use Lebytek\Framework\Domain\Entities\Usuario;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Domain\ValueObjects\Email;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;

final class UsuarioRepository extends BaseRepository implements UsuarioRepositoryInterface
{
    protected string $table = 'auth_usuarios';

    public function findById(int $id): ?Usuario
    {
        $row = $this->findRowById($id);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(Email $email): ?Usuario
    {
        $row = $this->queryOne(
            "SELECT * FROM auth_usuarios WHERE email = ? LIMIT 1",
            [(string) $email]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $rows = $this->query(
            "SELECT * FROM auth_usuarios ORDER BY nombre ASC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function countAll(): int
    {
        return parent::count();
    }

    public function save(Usuario $usuario): int
    {
        return $this->insert(
            "INSERT INTO auth_usuarios (nombre, apellido, email, password, activo, avatar, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $usuario->nombre(),
                $usuario->apellido(),
                (string) $usuario->email(),
                $usuario->passwordHash(),
                $usuario->activo() ? 1 : 0,
                $usuario->avatar(),
            ]
        );
    }

    public function update(Usuario $usuario): void
    {
        $this->execute(
            "UPDATE auth_usuarios
             SET nombre = ?, apellido = ?, email = ?, password = ?, activo = ?,
                 avatar = ?, ultimo_acceso = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $usuario->nombre(),
                $usuario->apellido(),
                (string) $usuario->email(),
                $usuario->passwordHash(),
                $usuario->activo() ? 1 : 0,
                $usuario->avatar(),
                $usuario->ultimoAcceso()?->format('Y-m-d H:i:s'),
                $usuario->id(),
            ]
        );
    }

    public function actualizarAvatar(int $usuarioId, ?string $ruta): void
    {
        $this->execute(
            "UPDATE auth_usuarios SET avatar = ?, updated_at = NOW() WHERE id = ?",
            [$ruta, $usuarioId]
        );
    }

    public function marcarEmailVerificado(int $usuarioId): void
    {
        $this->execute(
            "UPDATE auth_usuarios SET activo = 1, email_verificado_en = NOW(), updated_at = NOW() WHERE id = ?",
            [$usuarioId]
        );
    }

    public function delete(int $id): void
    {
        parent::softDelete($id);
    }

    public function emailExists(Email $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $count = $this->queryOne(
                "SELECT COUNT(*) AS cnt FROM auth_usuarios WHERE email = ? AND id != ?",
                [(string) $email, $excludeId]
            );
        } else {
            $count = $this->queryOne(
                "SELECT COUNT(*) AS cnt FROM auth_usuarios WHERE email = ?",
                [(string) $email]
            );
        }
        return ((int) ($count['cnt'] ?? 0)) > 0;
    }

    private function hydrate(array $row): Usuario
    {
        return new Usuario(
            nombre:       $row['nombre'],
            apellido:     $row['apellido'],
            email:        new Email($row['email']),
            passwordHash: $row['password'],
            activo:       (bool) $row['activo'],
            avatar:       $row['avatar'] ?? null,
            ultimoAcceso: $row['ultimo_acceso']
                ? new \DateTimeImmutable($row['ultimo_acceso'])
                : null,
            emailVerificadoEn: !empty($row['email_verificado_en'])
                ? new \DateTimeImmutable($row['email_verificado_en'])
                : null,
            creadoEn: new \DateTimeImmutable($row['created_at']),
            id:       (int) $row['id']
        );
    }
}
