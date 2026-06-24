<?php
declare(strict_types=1);

use App\Domain\Pdf\PdfBlock;
use App\Domain\Pdf\PdfHeader;
use App\Domain\Pdf\PdfText;
use App\Domain\Pdf\PdfDataTable;
use App\Domain\Pdf\PdfIndicatorCard;
use App\Domain\Pdf\PdfTotalsBlock;
use App\Domain\Pdf\PdfSignatureBlock;
use App\Domain\Pdf\PdfLogo;
use App\Domain\Pdf\PdfFooter;
use App\Domain\Pdf\PdfSpacer;
use App\Domain\Pdf\PdfPageBreak;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Pdf\PdfPageSetup;

test('cada componente es un PdfBlock y reporta su type()', function (): void {
    $blocks = [
        'header'    => new PdfHeader('Título', 'Sub'),
        'logo'      => new PdfLogo('/tmp/logo.png', 40),
        'text'      => new PdfText('hola'),
        'table'     => new PdfDataTable([['name' => 'id', 'label' => 'N°']], [['id' => 1]]),
        'indicator' => new PdfIndicatorCard('Total', '10', 'money'),
        'totals'    => new PdfTotalsBlock([['label' => 'Total', 'value' => 5, 'format' => 'money']]),
        'signature' => new PdfSignatureBlock(['Firma cliente']),
        'footer'    => new PdfFooter('pie'),
        'spacer'    => new PdfSpacer(20),
        'pagebreak' => new PdfPageBreak(),
    ];
    foreach ($blocks as $expectedType => $block) {
        assert_true($block instanceof PdfBlock, get_class($block) . ' no es PdfBlock');
        assert_same($expectedType, $block->type());
    }
});

test('PdfDataTable conserva columnas y filas', function (): void {
    $t = new PdfDataTable(
        [['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        [['total' => 1200.5]]
    );
    assert_same('money', $t->columns()[0]['format']);
    assert_same(1200.5, $t->rows()[0]['total']);
});

test('PdfText normaliza estilo desconocido a normal', function (): void {
    assert_same('normal', (new PdfText('x', 'fancy'))->style());
    assert_same('bold', (new PdfText('x', 'bold'))->style());
});

test('PdfDocument acumula bloques en orden y expone su setup', function (): void {
    $doc = PdfDocument::make(new PdfPageSetup('A4', 'landscape'))
        ->add(new PdfHeader('Reporte'))
        ->add(new PdfText('cuerpo'))
        ->add(new PdfFooter('pie'));

    assert_same('landscape', $doc->setup()->orientation());
    assert_same(3, count($doc->blocks()));
    assert_same('header', $doc->blocks()[0]->type());
    assert_same('footer', $doc->blocks()[2]->type());
});

test('PdfDocument::make sin setup usa A4 vertical por defecto', function (): void {
    $doc = PdfDocument::make();
    assert_same('A4', $doc->setup()->size());
    assert_same([], $doc->blocks());
});
