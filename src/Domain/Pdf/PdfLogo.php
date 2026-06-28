<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/** Logo por ruta local o data-URI (nunca URL remota: dompdf va con isRemoteEnabled=false). */
final class PdfLogo implements PdfBlock
{
    public function __construct(
        private readonly string $src,
        private readonly int $height = 40,
    ) {}

    public function type(): string { return 'logo'; }
    public function src(): string { return $this->src; }
    public function height(): int { return $this->height; }
}
