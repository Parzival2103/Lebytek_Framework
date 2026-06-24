<?php
declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/pdf_templates.php';

use App\Application\Pdf\PdfComponentRenderer;
use App\Application\Pdf\PdfRenderingService;
use App\Application\Pdf\PdfTemplateRegistry;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfEngineInterface;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfPageSetup;

/** Motor falso: captura el HTML y la configuración recibidos. */
final class SpyEngine implements PdfEngineInterface
{
    public string $html = '';
    public ?PdfPageSetup $setup = null;

    public function render(string $html, PdfPageSetup $setup): string
    {
        $this->html = $html;
        $this->setup = $setup;
        return "%PDF-fake";
    }
}

function prs_service(PdfEngineInterface $engine): PdfRenderingService
{
    return new PdfRenderingService(
        new PdfComponentRenderer(),
        $engine,
        new PdfTemplateRegistry(['ok' => FixtureOkTemplate::class]),
        ['font' => 'DejaVu Sans']
    );
}

test('renderDocument envuelve los bloques en el esqueleto y pasa el setup', function (): void {
    $spy = new SpyEngine();
    $doc = PdfDocument::make(new PdfPageSetup('A4', 'landscape'))->add(new PdfHeader('Reporte'));

    $bytes = prs_service($spy)->renderDocument($doc);

    assert_same('%PDF-fake', $bytes);
    assert_true(str_contains($spy->html, '<html'), 'incluye esqueleto html');
    assert_true(str_contains($spy->html, 'Reporte'), 'incluye el cuerpo renderizado');
    assert_same('landscape', $spy->setup?->orientation());
});

test('renderTemplate resuelve la clave, compone y renderiza', function (): void {
    $spy = new SpyEngine();
    $bytes = prs_service($spy)->renderTemplate('ok', ['title' => 'Desde plantilla']);

    assert_same('%PDF-fake', $bytes);
    assert_true(str_contains($spy->html, 'Desde plantilla'), 'compose() usó el payload');
});

test('renderTemplate con motor real produce %PDF', function (): void {
    $service = new PdfRenderingService(
        new PdfComponentRenderer(),
        new \App\Infrastructure\Pdf\DompdfRenderer(),
        new PdfTemplateRegistry(['ok' => FixtureOkTemplate::class]),
        []
    );
    $bytes = $service->renderTemplate('ok', ['title' => 'Real']);
    assert_same('%PDF', substr($bytes, 0, 4));
});

test('la plantilla demo registrada en config produce un PDF de colección', function (): void {
    $map = require ROOT_PATH . '/config/pdf_templates.php';
    $pdfConfig = require ROOT_PATH . '/config/pdf.php';

    $service = new PdfRenderingService(
        new PdfComponentRenderer(),
        new \App\Infrastructure\Pdf\DompdfRenderer(),
        new PdfTemplateRegistry($map),
        $pdfConfig
    );

    $bytes = $service->renderTemplate('demo_reporte', [
        'titulo'  => 'Pedidos del mes',
        'columns' => [['name' => 'cliente', 'label' => 'Cliente'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        'rows'    => [['cliente' => 'Ana', 'total' => 1200.5], ['cliente' => 'Beto', 'total' => 980]],
    ]);

    assert_same('%PDF', substr($bytes, 0, 4));
});
