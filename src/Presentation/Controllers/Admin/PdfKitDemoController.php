<?php

declare(strict_types=1);

namespace Lebytek\Framework\Presentation\Controllers\Admin;

use Lebytek\Framework\Application\Pdf\PdfKitDemoData;
use Lebytek\Framework\Application\Pdf\PdfRenderingService;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Vertical\VerticalProfile;
use Lebytek\Framework\Presentation\Controllers\AdminBaseController;

/**
 * Vistas demo del módulo pdf-kit: previsualización de componentes y descarga PDF.
 */
final class PdfKitDemoController extends AdminBaseController
{
    public function __construct(
        ConfiguracionService $configuracionService,
        AdminNavigationMenuService $adminNavigationMenuService,
        private readonly BuildPdfKitDemoViewModelUseCase $buildDemoViewModel,
        private readonly PdfRenderingService $pdfRenderingService,
    ) {
        parent::__construct($configuracionService, $adminNavigationMenuService);
    }

    public function index(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $data = $this->buildDemoViewModel->execute();
        return $this->view('admin/pdf_kit/demo/index', $data);
    }

    public function descargarReporte(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $bytes = $this->pdfRenderingService->renderTemplate(
            'demo_reporte',
            PdfKitDemoData::demoReportePayload(),
        );

        return Response::download($bytes, 'demo-reporte.pdf', 'application/pdf');
    }

    public function descargarCompleto(Request $request): Response
    {
        if (!$this->moduloHabilitado()) {
            return Response::notFound();
        }

        $bytes = $this->pdfRenderingService->renderDocument(
            PdfKitDemoData::buildDocumentoCompleto(),
        );

        return Response::download($bytes, 'demo-componentes-completo.pdf', 'application/pdf');
    }

    private function moduloHabilitado(): bool
    {
        return VerticalProfile::moduleEnabled('pdf_kit');
    }
}
