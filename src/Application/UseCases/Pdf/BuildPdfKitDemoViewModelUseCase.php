<?php

declare(strict_types=1);

namespace App\Application\UseCases\Pdf;

use App\Application\Pdf\PdfComponentRenderer;
use App\Application\Pdf\PdfKitDemoData;

/**
 * Arma el view-model de la vista demo del Kit de PDF: payload demo_reporte,
 * previsualizaciones HTML por componente y URLs de descarga.
 */
final class BuildPdfKitDemoViewModelUseCase
{
    public function __construct(
        private readonly PdfComponentRenderer $renderer,
    ) {}

    /** @return array<string,mixed> */
    public function execute(): array
    {
        $componentes = [];
        foreach (PdfKitDemoData::bloquesParaPrevisualizar() as $item) {
            $componentes[] = [
                'slug'        => $item['slug'],
                'type'        => $item['type'],
                'label'       => $item['label'],
                'descripcion' => $item['descripcion'],
                'html'        => $this->renderer->renderBlock($item['block']),
            ];
        }

        $payload = PdfKitDemoData::demoReportePayload();

        return [
            'titulo'              => 'Kit de PDF — Demostración',
            'subtitulo'           => 'Previsualización de componentes y descarga con la plantilla demo_reporte.',
            'demoPayload'         => $payload,
            'componentes'         => $componentes,
            'urlDescargaReporte'  => '/admin/pdf-kit/demo/descargar-reporte',
            'urlDescargaCompleto' => '/admin/pdf-kit/demo/descargar-completo',
            'plantillaClave'      => 'demo_reporte',
        ];
    }
}
