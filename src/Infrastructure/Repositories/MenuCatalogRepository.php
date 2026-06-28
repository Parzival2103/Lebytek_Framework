<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Repositories;

use Lebytek\Framework\Domain\Interfaces\MenuCatalogRepositoryInterface;
use Lebytek\Framework\Kernel\BaseClasses\BaseRepository;

final class MenuCatalogRepository extends BaseRepository implements MenuCatalogRepositoryInterface
{
    protected string $table = 'core_menu_items';

    public function obtenerArbolParaVista(): array
    {
        $sql = <<<SQL
            SELECT id, parent_id, orden, slug, label, icon, url, `match`,
                   permiso_slug, vertical_module
              FROM {$this->table}
             WHERE activo = 1
             ORDER BY orden ASC, id ASC
            SQL;

        $rows = $this->query($sql);
        /** @var array<int|string, list<array<string, mixed>>> $byParent */
        $byParent = [];
        $rootBucket = '__root__';

        foreach ($rows as $row) {
            $pidKey = $row['parent_id'] === null ? $rootBucket : (int) $row['parent_id'];
            if (! isset($byParent[$pidKey])) {
                $byParent[$pidKey] = [];
            }
            $byParent[$pidKey][] = $row;
        }

        foreach ($byParent as &$group) {
            usort($group, static fn(array $a, array $b): int => ($a['orden'] <=> $b['orden']));
        }
        unset($group);

        $roots = $byParent[$rootBucket] ?? [];

        return array_map(fn(array $root): array => $this->mapearRaizRecursiva($root, $byParent), $roots);
    }

    public function listarSlugsPermisoReferenciadosEnMenu(): array
    {
        $rows = $this->query(
            "SELECT DISTINCT `permiso_slug` AS s FROM {$this->table}
              WHERE activo = 1 AND `permiso_slug` IS NOT NULL AND TRIM(`permiso_slug`) <> ''"
        );
        $out = [];
        foreach ($rows as $row) {
            $s = trim((string) ($row['s'] ?? ''));
            if ($s !== '') {
                $out[$s] = true;
            }
        }

        $slugs = array_keys($out);
        sort($slugs);

        return $slugs;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<null|int, list<array<string, mixed>>> $byParent
     *
     * @return array<string, mixed>
     */
    private function mapearRaizRecursiva(array $row, array &$byParent): array
    {
        $id = (int) $row['id'];

        $item = [
            'id'    => $row['slug'],
            'label' => $row['label'],
            'icon'  => $row['icon'],
        ];

        if ($row['url'] !== null && $row['url'] !== '') {
            $item['url'] = $row['url'];
        }

        if ($row['match'] !== null && $row['match'] !== '') {
            $item['match'] = $row['match'];
        }

        if ($row['permiso_slug'] !== null && $row['permiso_slug'] !== '') {
            $item['permiso'] = $row['permiso_slug'];
        }

        if ($row['vertical_module'] !== null && $row['vertical_module'] !== '') {
            $item['vertical_module'] = $row['vertical_module'];
        }

        $hijosDirectos = $byParent[$id] ?? [];

        foreach ($hijosDirectos as $child) {
            /* Un nivel de hijos bajo la raíz; las vistas solo renderizan un submenú plano */
            $item['submenu'][] = $this->mapearSubitemHoja($child);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapearSubitemHoja(array $row): array
    {
        $sub = [
            'label' => $row['label'],
            'icon'  => $row['icon'] ?? '',
            'url'   => $row['url'] ?? '',
        ];

        if ($row['permiso_slug'] !== null && $row['permiso_slug'] !== '') {
            $sub['permiso'] = $row['permiso_slug'];
        }

        if ($row['vertical_module'] !== null && $row['vertical_module'] !== '') {
            $sub['vertical_module'] = $row['vertical_module'];
        }

        return $sub;
    }
}
