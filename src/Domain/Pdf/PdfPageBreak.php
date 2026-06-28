<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

final class PdfPageBreak implements PdfBlock
{
    public function type(): string { return 'pagebreak'; }
}
