<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Avatares;

use Lebytek\Framework\Domain\Entities\Archivo;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Interfaces\ArchivoRepositoryInterface;

/*
|--------------------------------------------------------------------------
| AvatarOwnership — Validación de pertenencia del archivo de avatar
|--------------------------------------------------------------------------
| Regla compartida por fijar/eliminar: el archivo existe, no está
| borrado y pertenece a la colección avatar del usuario objetivo.
*/

final class AvatarOwnership
{
    public static function assert(ArchivoRepositoryInterface $archivos, int $usuarioId, int $archivoId): Archivo
    {
        $archivo = $archivos->buscarPorId($archivoId);

        if (
            $archivo === null
            || $archivo->deletedAt() !== null
            || $archivo->entidadTipo() !== AvatarDefaults::ENTIDAD_TIPO
            || $archivo->entidadId() !== $usuarioId
            || $archivo->coleccion() !== AvatarDefaults::COLECCION
        ) {
            throw new ValidationException('El avatar indicado no existe o no pertenece al usuario.');
        }

        return $archivo;
    }

    private function __construct()
    {
    }
}
