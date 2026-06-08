<?php

declare(strict_types=1);

namespace App\Application\Install;

final class InstallPlan
{
    /**
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $migracionesPendientes
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $seedsPendientes
     * @param list<array{clave:string,version:string}> $modulos
     * @param list<array{modulo:string,archivo:string}> $checksumsModificados
     */
    public function __construct(
        public readonly bool $nueva,
        public readonly array $migracionesPendientes,
        public readonly array $seedsPendientes,
        public readonly array $modulos,
        public readonly array $checksumsModificados,
    ) {}

    public function vacio(): bool
    {
        return $this->migracionesPendientes === [] && $this->seedsPendientes === [];
    }
}
