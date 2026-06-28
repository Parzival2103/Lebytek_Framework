<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\ReporteConfigLoader;
use Lebytek\Framework\Application\Reporte\ReporteConfigValidator;

function cat_loader(): ReporteConfigLoader
{
    return new ReporteConfigLoader(new ReporteConfigValidator());
}

test('la fuente pedidos carga y declara modo registro', function (): void {
    $f = cat_loader()->load('pedidos');
    assert_same('demo_pedidos', $f->resource());
    assert_true(in_array('ticket_compra', $f->templatesFor('registro'), true));
    assert_true(in_array('presupuesto', $f->templatesFor('registro'), true));
    assert_same(['cliente', 'items'], $f->relationNames());
    assert_true($f->hasPeriod(), 'pedidos debe tener period.field');
});

test('la fuente productos carga y agrupa por categoria_id y status', function (): void {
    $f = cat_loader()->load('productos');
    assert_same('demo_productos', $f->resource());
    assert_true(in_array('categoria_id', $f->groupBy(), true));
    assert_true(in_array('status', $f->groupBy(), true));
    assert_true($f->allowsTreatment('precio_venta', 'sum'), 'precio_venta debe permitir sum');
    assert_true($f->allowsTreatment('stock_actual', 'sum'), 'stock_actual debe permitir sum');
});

test('la fuente clientes carga y declara contrato en registro', function (): void {
    $f = cat_loader()->load('clientes');
    assert_same('demo_clientes', $f->resource());
    assert_true(in_array('contrato', $f->templatesFor('registro'), true));
    assert_same([], $f->relationNames());
});

test('listFuentes incluye las cuatro fuentes', function (): void {
    $keys = array_keys(cat_loader()->listFuentes());
    sort($keys);
    assert_same(['citas', 'clientes', 'pedidos', 'productos'], $keys);
});
