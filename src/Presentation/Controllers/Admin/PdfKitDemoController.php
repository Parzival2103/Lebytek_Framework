<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Admin;

use App\Application\Pdf\PdfKitDemoData;
use App\Application\Pdf\PdfRenderingService;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\ConfiguracionService;
use App\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Vertical\VerticalProfile;
use App\Presentation\Controllers\AdminBaseController;

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
