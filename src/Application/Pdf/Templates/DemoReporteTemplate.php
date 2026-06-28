<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf\Templates;

use Lebytek\Framework\Domain\Pdf\PdfDataTable;
use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfFooter;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Lebytek\Framework\Domain\Pdf\PdfSpacer;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;

/**
 * Plantilla demo de colección: cabecera + tabla de filas. Sirve de ejemplo mínimo
 * del kit y de objetivo de prueba para Fase 0. Reportes (Fase 1) aporta plantillas
 * más ricas; el HTML/diseño siempre lo define el programador, nunca el usuario.
 */
final class DemoReporteTemplate implements PdfTemplateInterface
{
    public function compose(array $payload): PdfDocument
    {
        $titulo  = (string) ($payload['titulo'] ?? 'Reporte');
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $rows    = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        return PdfDocument::make(new PdfPageSetup('A4', 'portrait'))
            ->add(new PdfHeader($titulo, 'Documento de demostración del Kit de PDF'))
            ->add(new PdfSpacer(8))
            ->add(new PdfDataTable($columns, $rows))
            ->add(new PdfFooter('Generado por el Kit de PDF'));
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }
}
