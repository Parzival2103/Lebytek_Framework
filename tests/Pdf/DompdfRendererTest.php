<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Pdf\DompdfRenderer;
use Lebytek\Framework\Infrastructure\Pdf\PdfStorage;
use Lebytek\Framework\Domain\Pdf\PdfPageSetup;

test('DompdfRenderer produce bytes que empiezan con %PDF', function (): void {
    $bytes = (new DompdfRenderer())->render(
        '<html><body><h1>Hola</h1></body></html>',
        new PdfPageSetup('A4', 'portrait')
    );
    assert_true(strlen($bytes) > 100, 'el PDF no debe estar vacío');
    assert_same('%PDF', substr($bytes, 0, 4));
});

test('PdfStorage guarda bytes y devuelve una ruta legible', function (): void {
    $path = (new PdfStorage())->save("%PDF-1.7\nx", 'prueba demo.pdf');
    assert_true(is_readable($path), 'el archivo guardado debe existir');
    assert_same('%PDF', substr((string) file_get_contents($path), 0, 4));
    @unlink($path);
});
