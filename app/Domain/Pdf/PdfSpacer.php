<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfSpacer implements PdfBlock
{
    public function __construct(
        private readonly int $height = 12,
    ) {}

    public function type(): string { return 'spacer'; }
    public function height(): int { return $this->height; }
}
