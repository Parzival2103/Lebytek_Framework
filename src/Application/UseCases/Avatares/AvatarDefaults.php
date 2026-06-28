<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Avatares;

use Lebytek\Framework\Application\DTO\Files\ImageOptions;

/*
|--------------------------------------------------------------------------
| AvatarDefaults — Convenciones compartidas del módulo de avatares
|--------------------------------------------------------------------------
| Entidad/colección/directorio y opciones de imagen únicas para todos
| los casos de uso de avatar.
*/

final class AvatarDefaults
{
    public const ENTIDAD_TIPO = 'usuario';
    public const COLECCION    = 'avatar';
    public const DIRECTORIO   = 'uploads/avatars';

    public static function imageOptions(): ImageOptions
    {
        return new ImageOptions(maxWidth: 1024, maxHeight: 1024, calidad: 82);
    }

    private function __construct()
    {
    }
}
