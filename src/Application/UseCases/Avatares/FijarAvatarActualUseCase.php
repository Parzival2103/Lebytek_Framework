<?php

declare(strict_types=1);

namespace App\Application\UseCases\Avatares;

use App\Domain\Entities\Archivo;
use App\Domain\Interfaces\ArchivoRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;

/*
|--------------------------------------------------------------------------
| FijarAvatarActualUseCase — Promueve un avatar del historial a actual
|--------------------------------------------------------------------------
| Valida pertenencia e integridad (existe, es de la colección avatar del
| usuario y no está borrado), marca actual y refresca el cache.
*/

final class FijarAvatarActualUseCase
{
    public function __construct(
        private readonly ArchivoRepositoryInterface $archivos,
        private readonly UsuarioRepositoryInterface $usuarioRepo
    ) {
    }

    public function execute(int $usuarioId, int $archivoId, int $actorId): Archivo
    {
        $archivo = AvatarOwnership::assert($this->archivos, $usuarioId, $archivoId);

        $this->archivos->marcarActual($archivoId, AvatarDefaults::ENTIDAD_TIPO, $usuarioId, AvatarDefaults::COLECCION);
        $this->usuarioRepo->actualizarAvatar($usuarioId, $archivo->ruta());

        return $this->archivos->buscarPorId($archivoId) ?? $archivo;
    }
}
