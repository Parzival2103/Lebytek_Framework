<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Handlers;

use Lebytek\Framework\Application\Crud\Context\CrudValidationContext;
use Lebytek\Framework\Domain\Interfaces\CrudValidatorInterface;

/**
 * Demo de validador de formulario externo (escape hatch). Exige total > 0.
 */
final class DemoPedidoTotalValidator implements CrudValidatorInterface
{
    public function validate(CrudValidationContext $ctx): void
    {
        $normalized = $ctx->normalized();
        if (!array_key_exists('total', $normalized)) {
            return;
        }
        $total = (float) str_replace(',', '.', (string) $normalized['total']);
        if ($total <= 0) {
            $ctx->addError('total', 'El total del pedido debe ser mayor a cero.');
        }
    }
}
