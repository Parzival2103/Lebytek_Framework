<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Pdf\PdfPageSetup;

test('PdfPageSetup expone defaults A4 vertical', function (): void {
    $s = new PdfPageSetup();
    assert_same('A4', $s->size());
    assert_same('portrait', $s->orientation());
    assert_same(36, $s->margins()['top']);
});

test('PdfPageSetup normaliza orientación inválida a portrait', function (): void {
    $s = new PdfPageSetup('A4', 'diagonal');
    assert_same('portrait', $s->orientation());
});

test('PdfPageSetup::fromArray lee tamaño, orientación y márgenes', function (): void {
    $s = PdfPageSetup::fromArray([
        'size' => 'letter',
        'orientation' => 'landscape',
        'margins' => ['top' => 10, 'right' => 20, 'bottom' => 30, 'left' => 40],
    ]);
    assert_same('letter', $s->size());
    assert_same('landscape', $s->orientation());
    assert_same(20, $s->margins()['right']);
    assert_same('10px 20px 30px 40px', $s->marginsCss());
});
