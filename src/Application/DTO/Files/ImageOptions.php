<?php

declare(strict_types=1);

namespace App\Application\DTO\Files;

/*
|--------------------------------------------------------------------------
| ImageOptions — Opciones de post-procesado de imagen
|--------------------------------------------------------------------------
| Caja máxima de redimensión (nunca agranda) y calidad de recompresión.
*/

final class ImageOptions
{
    public function __construct(
        public readonly int $maxWidth,
        public readonly int $maxHeight,
        public readonly int $calidad,
        public readonly ?ThumbnailOptions $thumbnail = null
    ) {
    }
}
