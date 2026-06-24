<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

/** Marcador para todo componente atómico de un documento PDF. */
interface PdfBlock
{
    /** Slug estable del tipo de bloque (header, logo, text, table, ...). */
    public function type(): string;
}
