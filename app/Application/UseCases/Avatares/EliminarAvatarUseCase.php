<?php

declare(strict_types=1);

namespace App\Application\UseCases\Avatares;

use App\Domain\Interfaces\ArchivoRepositoryInterface;
use App\Domain\Interfaces\UsuarioRepositoryInterface;

/*
|--------------------------------------------------------------------------
| EliminarAvatarUseCase — Soft delete de un avatar del historial
|--------------------------------------------------------------------------
| No toca el archivo físico (depuración: fase 2). Si el borrado era el
| actual, el cache auth_usuarios.avatar queda en NULL.
*/

final class EliminarAvatarUseCase
{
    public function __construct(
        private readonly ArchivoRepositoryInterface $archivos,
        private readonly UsuarioRepositoryInterface $usuarioRepo
    ) {
    }

    public function execute(int $usuarioId, int $archivoId, int $actorId): void
    {
        $archivo = AvatarOwnership::assert($this->archivos, $usuarioId, $archivoId);

        $eraActual = $archivo->esActual();
        $this->archivos->softDelete($archivoId);

        if ($eraActual) {
            $this->usuarioRepo->actualizarAvatar($usuarioId, null);
        }
    }
}
