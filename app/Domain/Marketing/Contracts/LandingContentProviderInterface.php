<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface LandingContentProviderInterface
{
    /**
     * Bloques de contenido de una página pública, indexados por clave.
     * @return array<string,array<string,mixed>>  ej: ['hero'=>['titulo'=>...]]
     */
    public function getBloques(string $pagina): array;
}
