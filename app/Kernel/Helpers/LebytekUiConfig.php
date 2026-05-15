<?php

declare(strict_types=1);

namespace App\Kernel\Helpers;

use App\Kernel\Constants\AppConstants;

/**
 * Resuelve tokens visuales LEBYTEK UI desde cfg_configuraciones (claves planas o con prefijos).
 * Sin acceso a base de datos: solo transforma el arreglo ya cargado.
 */
final class LebytekUiConfig
{
    /**
     * @param  array<string, mixed> $cfg
     * @param  list<string>         $keys
     */
    public static function firstValue(array $cfg, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $cfg)) {
                continue;
            }
            $v = $cfg[$key];
            if ($v === null || $v === '') {
                continue;
            }
            return $v;
        }

        return $default;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultStatusBadges(): array
    {
        return [
            'activo' => 'success',
            'inactivo' => 'secondary',
            'pendiente' => 'warning',
            'cancelado' => 'danger',
            'borrado' => 'dark',
            'bloqueado' => 'danger',
            'procesando' => 'info',
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function globalTableCompact(array $cfg): bool
    {
        $v = strtolower((string) self::firstValue($cfg, [
            'ui.table_density',
            'ui_table_density',
            'table_density',
        ], 'normal'));

        return $v === 'compact';
    }

    /**
     * @param array<string, mixed> $sysConfig
     * @return array<string, mixed>
     */
    public static function resolve(array $sysConfig): array
    {
        $primaryColor = (string) self::firstValue($sysConfig, [
            'theme.primary_color',
            'theme_primary_color',
            AppConstants::CONFIG_PRIMARY_COLOR,
        ], '#0d6efd');

        $navbarColor = (string) self::firstValue($sysConfig, [
            'theme.sidebar_bg',
            'theme_sidebar_bg',
            'theme.topbar_bg',
            'theme_topbar_bg',
            'navbar_color',
        ], '#1a1d2e');

        $bodyColor = (string) self::firstValue($sysConfig, [
            'theme.body_bg',
            'body_color',
        ], '#f0f2f5');

        $darkMode = !empty($sysConfig[AppConstants::CONFIG_DARK_MODE]) && $sysConfig[AppConstants::CONFIG_DARK_MODE] === '1';

        $customNavText = self::firstValue($sysConfig, [
            'theme.sidebar_text',
            'theme_sidebar_text',
        ], null);

        if (is_string($customNavText) && $customNavText !== '') {
            $navbarColors = [
                'text' => $customNavText,
                'muted' => 'rgba(255,255,255,0.45)',
                'separator' => 'rgba(255,255,255,0.1)',
            ];
        } else {
            $navbarColors = ViewHelper::navbarTextColors($navbarColor);
        }

        $radius = self::normalizeRadius(self::firstValue($sysConfig, [
            'theme.border_radius',
            'theme_border_radius',
            'border_radius',
        ], null));

        $shadowLevel = (int) self::firstValue($sysConfig, [
            'theme.shadow_level',
            'theme_shadow_level',
            'shadow_level',
        ], 1);
        $shadowCss = self::shadowCss($shadowLevel);

        $layoutWidth = strtolower((string) self::firstValue($sysConfig, [
            'ui.layout_width',
            'ui_layout_width',
            'layout_width',
        ], 'fluid'));

        $contentDensity = strtolower((string) self::firstValue($sysConfig, [
            'ui.content_density',
            'ui_content_density',
            'content_density',
        ], 'comfortable'));

        $cardStyle = strtolower((string) self::firstValue($sysConfig, [
            'ui.card_style',
            'ui_card_style',
            'card_style',
        ], 'soft'));

        $rawAnim = self::firstValue($sysConfig, [
            'ui.enable_animations',
            'ui_enable_animations',
            'enable_animations',
        ], '1');
        $animationsOn = !in_array(strtolower((string) $rawAnim), ['0', 'false', 'off', 'no'], true);

        $menuLayout = (string) self::firstValue($sysConfig, [
            'ui.menu_position',
            AppConstants::CONFIG_MENU_LAYOUT,
        ], AppConstants::MENU_LAYOUT_SIDE);

        $lebytekBodyClasses = trim(implode(' ', array_filter([
            $layoutWidth === 'boxed' ? 'ct-layout-boxed' : 'ct-layout-fluid',
            $contentDensity === 'compact' ? 'ct-density-compact' : 'ct-density-comfortable',
            match ($cardStyle) {
                'bordered' => 'ct-card-bordered',
                'flat' => 'ct-card-flat',
                default => 'ct-card-soft',
            },
            $animationsOn ? 'ct-animations-on' : 'ct-animations-off',
        ])));

        $lebytekCssVariables = [
            '--ct-primary' => $primaryColor,
            '--ct-sidebar-bg' => $navbarColor,
            '--ct-topbar-bg' => (string) self::firstValue($sysConfig, [
                'theme.topbar_bg',
                'theme_topbar_bg',
            ], $navbarColor),
            '--ct-sidebar-text' => $navbarColors['text'],
            '--ct-radius' => $radius,
            '--ct-radius-sm' => 'calc(' . $radius . ' * 0.65)',
            '--ct-shadow-card' => $shadowCss,
            '--ct-transition' => '0.2s ease',
        ];

        return [
            'menuLayout' => in_array($menuLayout, [
                AppConstants::MENU_LAYOUT_SIDE,
                AppConstants::MENU_LAYOUT_TOP,
                AppConstants::MENU_LAYOUT_BOTTOM,
            ], true) ? $menuLayout : AppConstants::MENU_LAYOUT_SIDE,
            'primaryColor' => $primaryColor,
            'primaryHover' => ViewHelper::colorAjustar($primaryColor, -28),
            'primaryActive' => ViewHelper::colorAjustar($primaryColor, -45),
            'primarySubtle' => ViewHelper::calcularPrimarySubtle($primaryColor),
            'primaryRgb' => ViewHelper::colorRgb($primaryColor),
            'navbarColor' => $navbarColor,
            'navbarText' => $navbarColors['text'],
            'navbarTextMuted' => $navbarColors['muted'],
            'navbarSeparator' => $navbarColors['separator'],
            'bodyColor' => $bodyColor,
            'bodyBg' => $darkMode ? '' : $bodyColor,
            'darkMode' => $darkMode,
            'empresaNombre' => (string) self::firstValue($sysConfig, [
                'theme.app_name',
                AppConstants::CONFIG_EMPRESA_NOMBRE,
            ], 'Sistema'),
            'empresaLogo' => (string) self::firstValue($sysConfig, [
                'theme.logo_path',
                AppConstants::CONFIG_EMPRESA_LOGO,
            ], ''),
            'lebytekBodyClasses' => $lebytekBodyClasses,
            'lebytekCssVariables' => $lebytekCssVariables,
            'lebytekLayoutWidth' => $layoutWidth,
            'lebytekContentDensity' => $contentDensity,
            'lebytekCardStyle' => $cardStyle,
            'lebytekAnimations' => $animationsOn,
        ];
    }

    private static function normalizeRadius(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.625rem';
        }
        $s = trim((string) $value);
        if (preg_match('/^\d+(\.\d+)?$/', $s) === 1) {
            return $s . 'px';
        }
        if (str_ends_with($s, 'px') || str_ends_with($s, 'rem')) {
            return $s;
        }

        return match (strtolower($s)) {
            'xs', 'sm' => '0.375rem',
            'md' => '0.625rem',
            'lg', 'xl' => '0.875rem',
            default => '0.625rem',
        };
    }

    private static function shadowCss(int $level): string
    {
        $level = max(0, min(3, $level));

        return match ($level) {
            0 => 'none',
            1 => '0 0.125rem 0.35rem rgba(0, 0, 0, 0.045)',
            2 => '0 0.25rem 0.6rem rgba(0, 0, 0, 0.08)',
            default => '0 0.5rem 1.2rem rgba(0, 0, 0, 0.12)',
        };
    }
}
