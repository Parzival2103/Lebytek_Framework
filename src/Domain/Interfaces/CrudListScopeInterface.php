<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

use Lebytek\Framework\Application\Crud\Context\CrudListContext;

interface CrudListScopeInterface
{
    public function apply(CrudListContext $ctx): void;
}
