<?php

declare(strict_types=1);

namespace App\Application\Pdf\Templates;

use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfTemplateInterface;

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
