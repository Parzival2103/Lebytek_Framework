<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

interface PlantillaRepositoryInterface
{
    /** @return array{id:int,clave:string,asunto:string,cuerpo:string,activo:int}|null */
    public function findActiveByClave(string $clave): ?array;
}
