<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Rol;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use App\Kernel\BaseClasses\BaseRepository;

final class RolRepository extends BaseRepository implements RolRepositoryInterface
{
    protected string $table = 'auth_roles';

    public function findById(int $id): ?Rol
    {
        $row = $this->findRowById($id);
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Rol
    {
        $row = $this->queryOne("SELECT * FROM auth_roles WHERE slug = ? LIMIT 1", [$slug]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $rows = $this->query("SELECT * FROM auth_roles ORDER BY nombre ASC");
        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(Rol $rol): int
    {
        return $this->insert(
            "INSERT INTO auth_roles (nombre, slug, descripcion, activo, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$rol->nombre(), (string) $rol->slug(), $rol->descripcion(), $rol->activo() ? 1 : 0]
        );
    }

    public function update(Rol $rol): void
    {
        $this->execute(
            "UPDATE auth_roles SET nombre = ?, slug = ?, descripcion = ?, activo = ? WHERE id = ?",
            [$rol->nombre(), (string) $rol->slug(), $rol->descripcion(), $rol->activo() ? 1 : 0, $rol->id()]
        );
    }

    public function delete(int $id): void
    {
        $this->execute("DELETE FROM auth_roles WHERE id = ?", [$id]);
    }

    public function buscarPorUsuarioId(int $usuarioId): array
    {
        $rows = $this->query(
            "SELECT r.* FROM auth_roles r
             INNER JOIN auth_usuarios_roles ur ON ur.rol_id = r.id
             WHERE ur.usuario_id = ? AND r.activo = 1",
            [$usuarioId]
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function asignarRolAUsuario(int $usuarioId, int $rolId): void
    {
        $this->execute(
            "INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)",
            [$usuarioId, $rolId]
        );
    }

    public function revocarRolDeUsuario(int $usuarioId, int $rolId): void
    {
        $this->execute(
            "DELETE FROM auth_usuarios_roles WHERE usuario_id = ? AND rol_id = ?",
            [$usuarioId, $rolId]
        );
    }

    public function sincronizarRolesDeUsuario(int $usuarioId, array $rolIds): void
    {
        $this->beginTransaction();
        try {
            $this->execute("DELETE FROM auth_usuarios_roles WHERE usuario_id = ?", [$usuarioId]);
            foreach ($rolIds as $rolId) {
                $this->execute(
                    "INSERT INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)",
                    [$usuarioId, (int) $rolId]
                );
            }
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function hydrate(array $row): Rol
    {
        return new Rol(
            nombre:      $row['nombre'],
            slug:        new Slug($row['slug']),
            descripcion: $row['descripcion'] ?? '',
            activo:      (bool) $row['activo'],
            id:          (int) $row['id']
        );
    }
}
