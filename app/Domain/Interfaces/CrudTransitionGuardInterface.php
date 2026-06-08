<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudTransitionContext;

/**
 * Guard de transición de estado. `authorize` lanza para bloquear la transición
 * (p. ej. reglas de negocio, permisos finos). No retorna nada: silencio = OK.
 */
interface CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void;
}
