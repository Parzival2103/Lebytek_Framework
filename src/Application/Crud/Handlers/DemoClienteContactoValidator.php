<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Handlers;

use Lebytek\Framework\Application\Crud\Context\CrudValidationContext;
use Lebytek\Framework\Domain\Interfaces\CrudValidatorInterface;

/**
 * Demo de validación cross-field: un cliente activo debe tener teléfono.
 */
final class DemoClienteContactoValidator implements CrudValidatorInterface
{
    public function validate(CrudValidationContext $ctx): void
    {
        $normalized = $ctx->normalized();
        $status = (string) ($normalized['status'] ?? '');
        $telefono = trim((string) ($normalized['telefono'] ?? ''));
        if ($status === 'activo' && $telefono === '') {
            $ctx->addError('telefono', 'Un cliente activo debe tener teléfono.');
        }
    }
}
