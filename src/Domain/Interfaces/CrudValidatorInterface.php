<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Application\Crud\Context\CrudValidationContext;

interface CrudValidatorInterface
{
    /** Agrega errores vía $ctx->addError(); no lanza por errores de validación. */
    public function validate(CrudValidationContext $ctx): void;
}
