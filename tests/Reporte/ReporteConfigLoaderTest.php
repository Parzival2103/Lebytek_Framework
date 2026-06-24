<?php
declare(strict_types=1);

use App\Application\Reporte\ReporteConfigLoader;
use App\Application\Reporte\ReporteConfigValidator;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;

function rcl_loader(): ReporteConfigLoader
{
    return new ReporteConfigLoader(new ReporteConfigValidator());
}

test('carga la fuente demo citas y devuelve un ReporteFuente', function (): void {
    $f = rcl_loader()->load('citas');
    assert_true($f instanceof ReporteFuente);
    assert_same('demo_citas', $f->resource());
    assert_true($f->hasColumn('estado'));
    assert_same('fecha_inicio', $f->periodField());
});

test('una clave inexistente lanza ValidationException', function (): void {
    assert_throws(ValidationException::class, fn() => rcl_loader()->load('no_existe'));
});

test('listFuentes incluye la fuente demo', function (): void {
    $fuentes = rcl_loader()->listFuentes();
    assert_true(array_key_exists('citas', $fuentes));
    assert_same('Citas', $fuentes['citas']);
});
