<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudActionContext;

interface CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void;
}
