<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface CommercialPackageSourceInterface
{
    /** @return list<array<string,mixed>> */
    public function listarPaquetes(): array;
}
