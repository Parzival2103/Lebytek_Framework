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
    public const CONFIG_EMPRESA_NOMBRE = 'empresa_nombre';
    public const CONFIG_EMPRESA_LOGO   = 'empresa_logo';

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
