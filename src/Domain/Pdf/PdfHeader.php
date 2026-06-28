<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

final class PdfHeader implements PdfBlock
{
    public function __construct(
        private readonly string $title,
        private readonly string $subtitle = '',
    ) {}

    public function type(): string { return 'header'; }
    public function title(): string { return $this->title; }
    public function subtitle(): string { return $this->subtitle; }
}
