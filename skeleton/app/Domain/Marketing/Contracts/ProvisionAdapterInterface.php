<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\Lead;
use App\Domain\Marketing\ValueObjects\Provision;
use App\Domain\Marketing\ValueObjects\ProvisionResult;

interface ProvisionAdapterInterface
{
    /** @param array<string,mixed> $credenciales */
    public function aprovisionar(Lead $lead, array $credenciales): ProvisionResult;

    /** @return array<string,mixed> */
    public function estado(Provision $p): array;
}
