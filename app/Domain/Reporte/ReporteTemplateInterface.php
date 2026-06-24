<?php
declare(strict_types=1);

namespace App\Domain\Reporte;

use App\Domain\Pdf\PdfTemplateInterface;

/**
 * Plantilla de reporte: además de componer un PdfDocument (PdfTemplateInterface),
 * declara el "schema de pasos" que el wizard usa para mostrar/ocultar pasos.
 */
interface ReporteTemplateInterface extends PdfTemplateInterface
{
    /**
     * @return array{modo:string,requiere_periodo:bool,permite_tratamientos:bool,min_columnas:int,max_columnas:int}
     */
    public function schemaPasos(): array;
}
