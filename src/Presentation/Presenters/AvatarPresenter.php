<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Presenters;

use Lebytek\Framework\Domain\Entities\Archivo;

/*
|--------------------------------------------------------------------------
| AvatarPresenter — Forma JSON estándar de las respuestas de avatar
|--------------------------------------------------------------------------
| Compartido por PerfilController y UsuariosController:
| { ok, actual: {id, ruta}|null, historial: [{id, ruta, esActual}] }
*/

final class AvatarPresenter
{
    /** @param Archivo[] $historial vigente, más reciente primero */
    public static function payload(array $historial): array
    {
        $actual = null;
        $items  = [];

        foreach ($historial as $archivo) {
            $item = [
                'id'       => $archivo->id(),
                'ruta'     => $archivo->ruta(),
                'esActual' => $archivo->esActual(),
            ];
            $items[] = $item;
            if ($archivo->esActual()) {
                $actual = ['id' => $archivo->id(), 'ruta' => $archivo->ruta()];
            }
        }

        return ['ok' => true, 'actual' => $actual, 'historial' => $items];
    }

    public static function error(string $mensaje): array
    {
        return ['ok' => false, 'error' => $mensaje];
    }

    private function __construct()
    {
    }
}
