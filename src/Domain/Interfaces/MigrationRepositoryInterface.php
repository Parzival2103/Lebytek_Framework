<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

interface MigrationRepositoryInterface
{
    /**
     * Archivos ya aplicados.
     *
     * @return array<string,string> archivo => checksum sha256
     */
    public function aplicadas(): array;

    public function registrar(string $modulo, string $archivo, string $checksum): void;

    public function existeTabla(string $nombre): bool;
}
