<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

/** Bloque de totales: lista de ['label','value','format'?]. */
final class PdfTotalsBlock implements PdfBlock
{
    /** @param list<array{label:string,value:mixed,format?:string}> $totals */
    public function __construct(
        private readonly array $totals,
    ) {}

    public function type(): string { return 'totals'; }

    /** @return list<array{label:string,value:mixed,format?:string}> */
    public function totals(): array { return $this->totals; }
}
