<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

final class PdfText implements PdfBlock
{
    private const STYLES = ['normal', 'muted', 'bold'];

    private string $style;

    public function __construct(
        private readonly string $text,
        string $style = 'normal',
    ) {
        $this->style = in_array($style, self::STYLES, true) ? $style : 'normal';
    }

    public function type(): string { return 'text'; }
    public function text(): string { return $this->text; }
    public function style(): string { return $this->style; }
}
