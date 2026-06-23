<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\CommercialPackageSourceInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;

final class CrudCommercialPackageSource implements CommercialPackageSourceInterface
{
    public function __construct(
        private readonly MarketingContentRepositoryInterface $repo
    ) {}

    public function listarPaquetes(): array
    {
        return $this->repo->paquetesActivos();
    }
}
