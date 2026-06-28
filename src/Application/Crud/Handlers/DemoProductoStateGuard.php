<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Domain\Exceptions\ValidationException;

/**
 * Guard demo: ejemplo de escape hatch para una transición de estado.
 * Permite reactivar un producto solo si venía de 'inactivo'. La lógica de
 * negocio vive aquí, nunca en los servicios Crud* del core.
 */
final class DemoProductoStateGuard implements CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void
    {
        if ($ctx->from() !== 'inactivo') {
            throw new ValidationException('Solo se puede activar un producto inactivo.');
        }
    }
}
