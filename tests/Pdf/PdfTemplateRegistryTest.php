<?php
declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/pdf_templates.php';

use Lebytek\Framework\Application\Pdf\PdfTemplateRegistry;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Pdf\PdfTemplateInterface;

function ptr_registry(): PdfTemplateRegistry
{
    return new PdfTemplateRegistry([
        'ok'       => FixtureOkTemplate::class,
        'broken'   => FixtureNotATemplate::class,
        'missing'  => 'Lebytek\Framework\\Nope\\DoesNotExist',
    ]);
}

test('resuelve una clave válida a una instancia PdfTemplateInterface', function (): void {
    $tpl = ptr_registry()->resolve('ok');
    assert_true($tpl instanceof PdfTemplateInterface, 'instancia de plantilla');
    assert_true($tpl->supports('coleccion'), 'soporta colección');
});

test('clave inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('fantasma'));
});

test('clase que no implementa la interfaz lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('broken'));
});

test('clase inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => ptr_registry()->resolve('missing'));
});

test('has() refleja presencia en el whitelist', function (): void {
    assert_true(ptr_registry()->has('ok'));
    assert_true(!ptr_registry()->has('fantasma'));
});
