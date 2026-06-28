<?php

declare(strict_types=1);

namespace App\Domain\Policies;

/*
|--------------------------------------------------------------------------
| AvatarPolicy — Autorización de gestión de avatares
|--------------------------------------------------------------------------
| Regla única: el dueño del avatar siempre puede gestionarlo; cualquier
| otro actor requiere el permiso usuarios.gestionar. Pura: el consumidor
| resuelve el booleano del permiso (RbacService) y el actor (sesión).
*/

final class AvatarPolicy
{
    public function puedeGestionar(int $actorId, int $usuarioObjetivoId, bool $puedeGestionarUsuarios): bool
    {
        return $actorId === $usuarioObjetivoId || $puedeGestionarUsuarios;
    }
}
