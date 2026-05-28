<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudTransitionContext;

interface CrudTransitionGuardInterface
{
    /** Lanzar una excepción bloquea la transición. */
    public function authorize(CrudTransitionContext $ctx): void;
}
