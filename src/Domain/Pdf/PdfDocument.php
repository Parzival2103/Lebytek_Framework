<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/** Documento PDF: una página configurada + una lista ordenada de bloques atómicos. */
final class PdfDocument
{
    /** @var list<PdfBlock> */
    private array $blocks = [];

    public function __construct(
        private readonly PdfPageSetup $setup,
    ) {}

    public static function make(?PdfPageSetup $setup = null): self
    {
        return new self($setup ?? new PdfPageSetup());
    }

    public function add(PdfBlock $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    public function setup(): PdfPageSetup { return $this->setup; }

    /** @return list<PdfBlock> */
    public function blocks(): array { return $this->blocks; }
}
