<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Pdf\PdfComponentRenderer;
use Lebytek\Framework\Domain\Pdf\PdfHeader;
use Lebytek\Framework\Domain\Pdf\PdfText;
use Lebytek\Framework\Domain\Pdf\PdfDataTable;
use Lebytek\Framework\Domain\Pdf\PdfTotalsBlock;
use Lebytek\Framework\Domain\Pdf\PdfPageBreak;

test('renderiza header escapando el título', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([new PdfHeader('<b>A&B</b>', 'sub')]);
    assert_true(str_contains($html, '&lt;b&gt;A&amp;B&lt;/b&gt;'), 'título debe ir escapado');
    assert_true(str_contains($html, 'sub'), 'subtítulo presente');
});

test('renderiza tabla con formato money y escapa contenido', function (): void {
    $table = new PdfDataTable(
        [['name' => 'cliente', 'label' => 'Cliente'], ['name' => 'total', 'label' => 'Total', 'format' => 'money']],
        [['cliente' => 'Ana <x>', 'total' => 1200.5]]
    );
    $html = (new PdfComponentRenderer())->renderBlocks([$table]);
    assert_true(str_contains($html, 'Ana &lt;x&gt;'), 'celda de texto escapada');
    assert_true(str_contains($html, '$1,200.50'), 'formato money aplicado');
});

test('formatea date y datetime', function (): void {
    $r = new PdfComponentRenderer();
    assert_same('2026-06-14', $r->formatValue('2026-06-14 09:30:00', 'date'));
    assert_same('2026-06-14 09:30', $r->formatValue('2026-06-14 09:30:00', 'datetime'));
    assert_same('1,234', $r->formatValue(1234, 'number'));
    assert_same('hola', $r->formatValue('hola', 'raw'));
});

test('renderiza totales y un page break', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([
        new PdfTotalsBlock([['label' => 'Total', 'value' => 50, 'format' => 'money']]),
        new PdfPageBreak(),
    ]);
    assert_true(str_contains($html, 'Total'), 'etiqueta total');
    assert_true(str_contains($html, '$50.00'), 'valor total con formato');
    assert_true(str_contains($html, 'page-break-after'), 'page break presente');
});

test('un PdfText con estilo desconocido cae a normal sin romper el HTML', function (): void {
    $html = (new PdfComponentRenderer())->renderBlocks([new PdfText('cuerpo', 'fancy')]);
    assert_true(str_contains($html, 'pdf-text-normal'), 'estilo normalizado');
});
