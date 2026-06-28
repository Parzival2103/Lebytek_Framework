<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Application\Crud\Context\CrudActionContext;

interface CrudActionHandlerInterface
{
    public function handle(CrudActionContext $ctx): void;
}
