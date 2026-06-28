<?php

declare(strict_types=1);

namespace App\Domain\Pdf;

/**
 * Tabla de datos. Columnas: lista de ['name','label','format'?]; el renderer aplica
 * el formato (money/date/datetime/number). Filas: lista de mapas columna=>valor.
 */
final class PdfDataTable implements PdfBlock
{
    /**
     * @param list<array{name:string,label:string,format?:string}> $columns
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
    ) {}

    public function type(): string { return 'table'; }

    /** @return list<array{name:string,label:string,format?:string}> */
    public function columns(): array { return $this->columns; }

    /** @return list<array<string,mixed>> */
    public function rows(): array { return $this->rows; }
}
