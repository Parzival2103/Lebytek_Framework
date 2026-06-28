<?php

declare(strict_types=1);

namespace App\Application\Crud\Handlers;

use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudValidatorInterface;

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
