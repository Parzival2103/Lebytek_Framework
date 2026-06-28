<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudTransitionContext;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\CrudTransitionGuardInterface;

/**
 * Demo de guard de transición (escape hatch). No permite pagar un pedido en
 * total cero (regla de negocio que vive fuera del core).
 */
final class DemoPedidoPagarGuard implements CrudTransitionGuardInterface
{
    public function authorize(CrudTransitionContext $ctx): void
    {
        $record = $ctx->record();
        $total = (float) ($record['total'] ?? 0);
        if ($total <= 0) {
            throw new ValidationException('No se puede pagar un pedido con total cero.');
        }
    }
}
