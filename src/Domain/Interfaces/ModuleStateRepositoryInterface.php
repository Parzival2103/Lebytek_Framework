<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

interface ModuleStateRepositoryInterface
{
    /**
     * Módulos registrados.
     *
     * @return array<string,array{version:string,activo:bool}> clave => estado
     */
    public function instalados(): array;

    public function registrar(string $clave, string $version, bool $activo): void;
}
