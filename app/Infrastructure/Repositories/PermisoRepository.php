<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Permiso;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use App\Kernel\BaseClasses\BaseRepository;

final class PermisoRepository extends BaseRepository implements PermisoRepositoryInterface
{
    protected string $table = 'auth_permisos';

    public function findById(int $id): ?Permiso
    {
        $row = $this->findRowById($id);
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Permiso
    {
        $row = $this->queryOne("SELECT * FROM auth_permisos WHERE slug = ? LIMIT 1", [$slug]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $rows = $this->query("SELECT * FROM auth_permisos ORDER BY modulo ASC, slug ASC");
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findAllActivosOrdenadosPorModuloSlug(): array
    {
        $rows = $this->query(
            'SELECT * FROM auth_permisos WHERE activo = 1 ORDER BY modulo ASC, slug ASC'
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function buscarPorRolId(int $rolId): array
    {
        $rows = $this->query(
            "SELECT p.* FROM auth_permisos p
             INNER JOIN auth_roles_permisos rp ON rp.permiso_id = p.id
             WHERE rp.rol_id = ? AND p.activo = 1
             ORDER BY p.modulo ASC, p.slug ASC",
            [$rolId]
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function slugsPorUsuarioId(int $usuarioId): array
    {
        $rows = $this->query(
            "SELECT DISTINCT p.slug FROM auth_permisos p
             INNER JOIN auth_roles_permisos rp ON rp.permiso_id = p.id
             INNER JOIN auth_usuarios_roles ur ON ur.rol_id = rp.rol_id
             WHERE ur.usuario_id = ? AND p.activo = 1",
            [$usuarioId]
        );
        return array_column($rows, 'slug');
    }

    public function filterExistingPermisoIds(array $permisoIds, bool $soloActivos = false): array
    {
        $ids = [];
        foreach ($permisoIds as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $ids[$i] = true;
            }
        }
        $unique = array_keys($ids);
        if ($unique === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $activoClause = $soloActivos ? ' AND activo = 1' : '';
        $sql          = "SELECT id FROM auth_permisos WHERE id IN ({$placeholders}){$activoClause}";
        $rows         = $this->query($sql, $unique);

        return array_map(static fn(array $r): int => (int) $r['id'], $rows);
    }

    public function mapSlugActivo(): array
    {
        $rows = $this->query('SELECT slug, activo FROM auth_permisos');
        $map  = [];
        foreach ($rows as $r) {
            $map[(string) $r['slug']] = (int) $r['activo'];
        }

        return $map;
    }

    public function listarTodosLosSlugs(): array
    {
        $rows = $this->query('SELECT slug FROM auth_permisos ORDER BY slug ASC');

        return array_values(array_unique(array_map(static fn(array $r): string => (string) $r['slug'], $rows)));
    }

    public function save(Permiso $permiso): int
    {
        return $this->insert(
            "INSERT INTO auth_permisos (nombre, slug, modulo, descripcion) VALUES (?, ?, ?, ?)",
            [
                $permiso->nombre(),
                (string) $permiso->slug(),
                $permiso->modulo(),
                $permiso->descripcion(),
            ]
        );
    }

    public function update(Permiso $permiso): void
    {
        $this->execute(
            "UPDATE auth_permisos SET nombre = ?, slug = ?, modulo = ?, descripcion = ? WHERE id = ?",
            [
                $permiso->nombre(),
                (string) $permiso->slug(),
                $permiso->modulo(),
                $permiso->descripcion(),
                $permiso->id(),
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->execute("DELETE FROM auth_permisos WHERE id = ?", [$id]);
    }

    public function sincronizarPermisosDeRol(int $rolId, array $permisoIds): void
    {
        $validIds = $this->filterExistingPermisoIds($permisoIds, true);
        $this->beginTransaction();
        try {
            $this->execute("DELETE FROM auth_roles_permisos WHERE rol_id = ?", [$rolId]);
            foreach ($validIds as $permisoId) {
                $this->execute(
                    "INSERT INTO auth_roles_permisos (rol_id, permiso_id) VALUES (?, ?)",
                    [$rolId, $permisoId]
                );
            }
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function hydrate(array $row): Permiso
    {
        return new Permiso(
            nombre:      $row['nombre'],
            slug:        new Slug($row['slug']),
            modulo:      $row['modulo']      ?? '',
            descripcion: $row['descripcion'] ?? '',
            id:          (int) $row['id']
        );
    }
}
