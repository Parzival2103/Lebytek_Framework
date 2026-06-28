<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfFooter implements PdfBlock
{
    public function __construct(
        private readonly string $text,
    ) {}

    public function type(): string { return 'footer'; }
    public function text(): string { return $this->text; }
}
