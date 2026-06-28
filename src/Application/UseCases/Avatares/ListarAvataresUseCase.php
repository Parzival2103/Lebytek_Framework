<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Avatares;

use Lebytek\Framework\Domain\Entities\Archivo;
use Lebytek\Framework\Domain\Interfaces\ArchivoRepositoryInterface;

/*
|--------------------------------------------------------------------------
| ListarAvataresUseCase — Historial vigente de avatares de un usuario
|--------------------------------------------------------------------------
| Devuelve los no borrados, más reciente primero, con el actual marcado
| (es_actual en cada Archivo).
*/

final class ListarAvataresUseCase
{
    public function __construct(
        private readonly ArchivoRepositoryInterface $archivos
    ) {
    }

    /** @return Archivo[] */
    public function execute(int $usuarioId): array
    {
        return $this->archivos->listarPorEntidad(
            AvatarDefaults::ENTIDAD_TIPO,
            $usuarioId,
            AvatarDefaults::COLECCION
        );
    }
}
