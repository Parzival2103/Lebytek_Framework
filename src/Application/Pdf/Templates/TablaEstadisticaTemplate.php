<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf\Templates;

use Lebytek\Framework\Domain\Pdf\PdfDataTable;
use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfFooter;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfLogo;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Lebytek\Framework\Domain\Pdf\PdfSpacer;
use Lebytek\Framework\Domain\Pdf\PdfTotalsBlock;
use Lebytek\Framework\Domain\Reporte\ReporteTemplateInterface;

/**
 * Plantilla demo de colección: encabezado con marca + periodo, tabla de datos
 * (agrupada o plana) y bloque de totales. Compone solo componentes del pdf-kit;
 * no genera HTML propio.
 */
final class TablaEstadisticaTemplate implements ReporteTemplateInterface
{
    /** @param array<string,mixed> $payload */
    public function compose(array $payload): PdfDocument
    {
        $orientation = (string) ($payload['orientation'] ?? 'portrait');
        $doc = PdfDocument::make(new PdfPageSetup('A4', $orientation));

        $marca = is_array($payload['marca'] ?? null) ? $payload['marca'] : [];
        $logo = (string) ($marca['logo'] ?? '');
        if ($logo !== '') {
            $doc->add(new PdfLogo($logo, 40));
        }

        $title = (string) ($payload['title'] ?? 'Reporte');
        $subtitle = trim((string) ($marca['empresa'] ?? '') . ' · ' . (string) ($payload['period'] ?? ''), ' ·');
        $doc->add(new PdfHeader($title, $subtitle));
        $doc->add(new PdfSpacer(8));

        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $doc->add(new PdfDataTable($columns, $rows));

        $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        if ($totals !== []) {
            $doc->add(new PdfSpacer(8));
            $doc->add(new PdfTotalsBlock($totals));
        }

        $doc->add(new PdfFooter('Generado por Lebytek · ' . date('Y-m-d H:i')));

        return $doc;
    }

    public function supports(string $mode): bool
    {
        return $mode === 'coleccion';
    }

    /** @return array{modo:string,requiere_periodo:bool,permite_tratamientos:bool,min_columnas:int,max_columnas:int} */
    public function schemaPasos(): array
    {
        return [
            'modo'                 => 'coleccion',
            'requiere_periodo'     => true,
            'permite_tratamientos' => true,
            'min_columnas'         => 1,
            'max_columnas'         => 12,
        ];
    }
}
