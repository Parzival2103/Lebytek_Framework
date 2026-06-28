<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/** Líneas de firma (cada string es una etiqueta bajo una línea para firmar). */
final class PdfSignatureBlock implements PdfBlock
{
    /** @param list<string> $labels */
    public function __construct(
        private readonly array $labels,
    ) {}

    public function type(): string { return 'signature'; }

    /** @return list<string> */
    public function labels(): array { return $this->labels; }
}
