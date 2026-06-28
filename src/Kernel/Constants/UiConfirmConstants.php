<?php

declare(strict_types=1);

namespace App\Kernel\Constants;

final class UiConfirmConstants
{
    public const DEFAULT_TITLE = 'Confirmar acción';
    public const DEFAULT_OK = 'Confirmar';
    public const DEFAULT_CANCEL = 'Cancelar';

    public const DELETE_TITLE = 'Confirmar eliminación';
    public const DELETE_BODY = 'Esta acción marca el registro como eliminado (borrado lógico). ¿Deseas continuar?';
    public const DELETE_OK = 'Eliminar';

    public const LOGOUT_TITLE = 'Cerrar sesión';
    public const LOGOUT_BODY = '¿Deseas cerrar la sesión actual?';
    public const LOGOUT_OK = 'Cerrar sesión';
    public const LOGOUT_ICON = 'warning';
}
