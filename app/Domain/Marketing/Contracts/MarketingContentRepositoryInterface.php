<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface MarketingContentRepositoryInterface
{
    /** @return array<string,array<string,mixed>> bloques indexados por clave */
    public function bloquesPorPagina(string $pagina): array;

    /** @return list<array<string,mixed>> */
    public function paquetesActivos(): array;
}
