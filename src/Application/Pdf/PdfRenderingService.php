<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Pdf;

use Lebytek\Framework\Domain\Pdf\PdfDocument;
use Lebytek\Framework\Domain\Pdf\PdfEngineInterface;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/**
 * Orquesta el render: arma el HTML (esqueleto + bloques) y delega en el motor para
 * obtener bytes. Punto de entrada para cualquier módulo que quiera emitir un PDF,
 * sea desde un PdfDocument ya armado o desde una plantilla registrada por clave.
 */
final class PdfRenderingService
{
    /** @param array<string,mixed> $config defaults de config/pdf.php (font, etc.) */
    public function __construct(
        private readonly PdfComponentRenderer $renderer,
        private readonly PdfEngineInterface $engine,
        private readonly PdfTemplateRegistry $registry,
        private readonly array $config = [],
    ) {}

    public function renderDocument(PdfDocument $document): string
    {
        $bodyHtml = $this->renderer->renderBlocks($document->blocks());
        $html = $this->wrap($bodyHtml);
        return $this->engine->render($html, $document->setup());
    }

    /** @param array<string,mixed> $payload */
    public function renderTemplate(string $key, array $payload): string
    {
        $document = $this->registry->resolve($key)->compose($payload);
        return $this->renderDocument($document);
    }

    private function wrap(string $bodyHtml): string
    {
        $font = (string) ($this->config['font'] ?? 'DejaVu Sans');
        ob_start();
        include ViewHelper::resolve('pdf/document');
        return (string) ob_get_clean();
    }
}
