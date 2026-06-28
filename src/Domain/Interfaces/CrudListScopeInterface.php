<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\Crud\Context\CrudListContext;

interface CrudListScopeInterface
{
    public function apply(CrudListContext $ctx): void;
}
