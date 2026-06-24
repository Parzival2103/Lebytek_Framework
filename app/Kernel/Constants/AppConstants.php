<?php

declare(strict_types=1);

namespace App\Kernel\Constants;

final class AppConstants
{
    // Paginacion
    public const PER_PAGE_DEFAULT = 20;
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    // Estados genericos
    public const ESTADO_ACTIVO   = 1;
    public const ESTADO_INACTIVO = 0;

    // Configuraciones clave
    public const CONFIG_MENU_LAYOUT    = 'menu_layout';
    public const CONFIG_PRIMARY_COLOR  = 'primary_color';
    public const CONFIG_DARK_MODE      = 'dark_mode';
    public const CONFIG_EMPRESA_NOMBRE        = 'empresa_nombre';
    public const CONFIG_EMPRESA_LOGO          = 'empresa_logo';
    public const CONFIG_EMPRESA_MOSTRAR_NOMBRE = 'empresa_mostrar_nombre';

    /** Nombre de marca por defecto cuando no hay valor en Admin → Ajustes. */
    public const EMPRESA_NOMBRE_DEFAULT = 'Framework Lebytek';

    /** Sufijo del footer cuando el nombre en ajustes es personalizado. */
    public const FOOTER_POWERED_BY = 'Powered by Lebytek';

    public static function resolveEmpresaNombre(?string $nombre): string
    {
        $trimmed = trim((string) $nombre);

        return $trimmed !== '' ? $trimmed : self::EMPRESA_NOMBRE_DEFAULT;
    }

    public static function footerMuestraPoweredBy(?string $nombre): bool
    {
        return self::resolveEmpresaNombre($nombre) !== self::EMPRESA_NOMBRE_DEFAULT;
    }

    /** Si el nombre visible junto al logo está activo en login y barras de navegación. */
    public static function empresaMostrarNombre(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if ($valor === null || $valor === '') {
            return true;
        }

        return !in_array(strtolower((string) $valor), ['0', 'false', 'off', 'no'], true);
    }

    // Menu layouts disponibles
    public const MENU_LAYOUT_SIDE   = 'side';
    public const MENU_LAYOUT_TOP    = 'top';
    public const MENU_LAYOUT_BOTTOM = 'bottom';

    // Tipos de flash
    public const FLASH_SUCCESS = 'success';
    public const FLASH_ERROR   = 'error';
    public const FLASH_WARNING = 'warning';
    public const FLASH_INFO    = 'info';
}
