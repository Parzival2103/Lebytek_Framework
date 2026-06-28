<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Application\Pdf\PdfRenderingService;
use App\Domain\Reporte\ReporteGuardado;
use App\Kernel\Config\Config;

/**
 * Genera los bytes PDF de un reporte de colección: construye el payload de datos,
 * le añade la marca (de config/cfg) y la orientación, y lo pasa a la plantilla vía
 * PdfRenderingService. La marca nunca proviene de datos de usuario.
 */
final class GenerarReporteUseCase
{
    public function __construct(
        private readonly BuildReporteDataUseCase $builder,
        private readonly PdfRenderingService $pdf,
    ) {}

    /** @return string bytes del PDF (empiezan con "%PDF"). */
    public function generar(ReporteGuardado $reporte, ?int $userId, callable $can): string
    {
        $payload = $this->builder->build($reporte, $userId, $can);
        $payload['marca'] = $this->marca();

        return $this->pdf->renderTemplate($reporte->templateKey(), $payload);
    }

    /** @return array<string,mixed> */
    private function marca(): array
    {
        $marca = Config::get('pdf.marca', []);
        return is_array($marca) ? $marca : [];
    }
}
