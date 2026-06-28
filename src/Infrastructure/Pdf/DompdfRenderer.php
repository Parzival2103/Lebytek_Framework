<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Pdf;

use Lebytek\Framework\Domain\Pdf\PdfEngineInterface;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Motor dompdf endurecido: sin recursos remotos, sin PHP embebido, chroot al repo
 * y fuentes locales. Recibe HTML ya escapado por el renderer; nunca HTML de usuario.
 */
final class DompdfRenderer implements PdfEngineInterface
{
    public function __construct(
        private readonly string $defaultFont = 'DejaVu Sans',
    ) {}

    public function render(string $html, PdfPageSetup $setup): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', ROOT_PATH);
        $options->set('defaultFont', $this->defaultFont);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($setup->size(), $setup->orientation());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
