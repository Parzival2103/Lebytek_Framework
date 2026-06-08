<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;

final class InstallerException extends RuntimeException
{
    public static function cicloDependencias(array $claves): self
    {
        return new self('Ciclo de dependencias entre módulos: ' . implode(' → ', $claves));
    }

    public static function manifiestoInvalido(string $detalle): self
    {
        return new self("Manifiesto inválido: {$detalle}");
    }
}
