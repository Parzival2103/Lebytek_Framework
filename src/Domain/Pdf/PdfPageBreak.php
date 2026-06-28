<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

final class PdfPageBreak implements PdfBlock
{
    public function type(): string { return 'pagebreak'; }
}
