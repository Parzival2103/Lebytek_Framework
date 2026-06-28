<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/** Tarjeta KPI: etiqueta + valor ya calculado + formato de presentación. */
final class PdfIndicatorCard implements PdfBlock
{
    public function __construct(
        private readonly string $label,
        private readonly string $value,
        private readonly string $format = 'raw',
    ) {}

    public function type(): string { return 'indicator'; }
    public function label(): string { return $this->label; }
    public function value(): string { return $this->value; }
    public function format(): string { return $this->format; }
}
