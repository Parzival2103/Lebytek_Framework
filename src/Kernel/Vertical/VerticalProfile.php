<?php

declare(strict_types=1);

namespace App\Kernel\Vertical;

use App\Kernel\Config\Config;

/*
|--------------------------------------------------------------------------
| VerticalProfile — Módulos y etiquetas por instancia
|--------------------------------------------------------------------------
| Lee config/vertical.php (vía Config) para filtrar el menú y personalizar
| textos por instancia sobre catálogo de core_menu_items (véase docs/modules/modulo-menu.md).
*/

final class VerticalProfile
{
    public static function moduleEnabled(string $moduleId): bool
    {
        if ($moduleId === '') {
            return true;
        }

        $modules = Config::get('vertical.modules', []);
        if (!is_array($modules) || !array_key_exists($moduleId, $modules)) {
            return true;
        }

        return (bool) $modules[$moduleId];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function filterMenuByModules(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $moduleId = (string) ($item['vertical_module'] ?? $item['id'] ?? '');
            if ($moduleId !== '' && !self::moduleEnabled($moduleId)) {
                continue;
            }

            if (!empty($item['submenu']) && is_array($item['submenu'])) {
                $filteredSub = [];
                foreach ($item['submenu'] as $sub) {
                    if (!is_array($sub)) {
                        continue;
                    }
                    $subModule = (string) ($sub['vertical_module'] ?? '');
                    if ($subModule !== '' && !self::moduleEnabled($subModule)) {
                        continue;
                    }
                    $filteredSub[] = $sub;
                }
                $item['submenu'] = $filteredSub;
            }

            if (!empty($item['submenu']) && $item['submenu'] === []) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function applyMenuLabels(array $items): array
    {
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? null;
            if (is_string($id) && $id !== '' && isset($item['label'])) {
                $item['label'] = self::menuLabel($id, (string) $item['label']);
            }
        }
        unset($item);

        return $items;
    }

    public static function menuLabel(string $menuId, string $defaultLabel): string
    {
        $custom = Config::get('vertical.labels.menu.' . $menuId);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $defaultLabel;
    }
}
