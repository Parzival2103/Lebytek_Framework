<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Pdf;

/**
 * Plantilla del programador: recibe un payload de datos y compone un PdfDocument.
 * El kit nunca recibe HTML de usuario; toda la estructura del documento vive aquí.
 */
interface PdfTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument;

    /** Modos soportados ('coleccion' | 'registro'); usado por Reportes para validar. */
    public function supports(string $mode): bool;
}
