<?php
declare(strict_types=1);

use App\Domain\Reporte\ReporteFuente;

test('ReporteFuente expone relationNames desde expose.relations', function (): void {
    $fuente = ReporteFuente::fromArray('pedidos', [
        'fuente'  => ['key' => 'pedidos', 'title' => 'Pedidos', 'resource' => 'demo_pedidos'],
        'modos'   => ['coleccion', 'registro'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'label' => 'Folio', 'type' => 'text']],
            'relations' => ['cliente', 'items'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['ticket_compra']],
    ]);

    assert_same(['cliente', 'items'], $fuente->relationNames());
});

test('ReporteFuente sin relaciones devuelve lista vacía', function (): void {
    $fuente = ReporteFuente::fromArray('clientes', [
        'fuente'  => ['key' => 'clientes', 'title' => 'Clientes', 'resource' => 'demo_clientes'],
        'expose'  => ['columns' => [['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text']], 'max_rows' => 5000],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['contrato']],
    ]);

    assert_same([], $fuente->relationNames());
});
