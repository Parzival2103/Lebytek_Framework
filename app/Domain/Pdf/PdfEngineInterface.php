<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

/** Motor de render: HTML + configuración de página → bytes del PDF. */
interface PdfEngineInterface
{
    /** @return string bytes binarios del PDF (empiezan con "%PDF"). */
    public function render(string $html, PdfPageSetup $setup): string;
}
