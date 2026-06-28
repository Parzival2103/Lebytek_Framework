<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\DTO\Files;

/*
|--------------------------------------------------------------------------
| ThumbnailOptions — Tamaño de miniatura (reservado fase 2)
|--------------------------------------------------------------------------
| Definido por contrato; ningún consumidor lo procesa todavía.
*/

final class ThumbnailOptions
{
    public function __construct(
        public readonly int $width,
        public readonly int $height
    ) {
    }
}
