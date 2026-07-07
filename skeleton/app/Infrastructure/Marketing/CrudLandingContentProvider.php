<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LandingContentProviderInterface;
use App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface;

final class CrudLandingContentProvider implements LandingContentProviderInterface
{
    public function __construct(
        private readonly MarketingContentRepositoryInterface $repo
    ) {}

    public function getBloques(string $pagina): array
    {
        return $this->repo->bloquesPorPagina($pagina);
    }
}
