<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LandingContentProviderInterface;
use App\Domain\Marketing\Contracts\CommercialPackageSourceInterface;

final class RenderLandingUseCase
{
    public function __construct(
        private readonly LandingContentProviderInterface $contenido,
        private readonly CommercialPackageSourceInterface $paquetes
    ) {}

    /** @return array{bloques: array<string,array<string,mixed>>, paquetes: list<array<string,mixed>>} */
    public function ejecutar(string $pagina = 'home'): array
    {
        return [
            'bloques'  => $this->contenido->getBloques($pagina),
            'paquetes' => $this->paquetes->listarPaquetes(),
        ];
    }
}
