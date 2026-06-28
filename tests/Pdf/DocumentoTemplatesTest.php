<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Pdf\Templates\ContratoTemplate;
use Lebytek\Framework\Application\Pdf\Templates\PresupuestoTemplate;
use Lebytek\Framework\Application\Pdf\Templates\TicketCompraTemplate;

/** @return list<string> tipos de bloque en orden */
function doc_types(\Lebytek\Framework\Domain\Pdf\PdfDocument $doc): array
{
    return array_map(static fn($b) => $b->type(), $doc->blocks());
}

function doc_pedido_payload(): array
{
    return [
        'orientation' => 'portrait',
        'title'       => 'Ticket',
        'marca'       => ['logo' => '', 'empresa' => 'ACME'],
        'record'      => ['folio' => 'PED-1', 'total' => '289.40', 'status' => 'pagado', 'cliente_id' => 7],
        'relations'   => [
            'cliente' => 'Juan Pérez',
            'items'   => [
                ['descripcion' => 'A', 'cantidad' => 1, 'precio_unitario' => '199.90', 'subtotal' => '199.90'],
                ['descripcion' => 'B', 'cantidad' => 1, 'precio_unitario' => '89.50', 'subtotal' => '89.50'],
            ],
        ],
    ];
}

test('TicketCompraTemplate soporta registro y no coleccion', function (): void {
    $t = new TicketCompraTemplate();
    assert_true($t->supports('registro'));
    assert_true(!$t->supports('coleccion'));
});

test('TicketCompraTemplate compone tabla de items, totales y footer', function (): void {
    $doc = (new TicketCompraTemplate())->compose(doc_pedido_payload());
    $types = doc_types($doc);
    assert_true(in_array('header', $types, true));
    assert_true(in_array('table', $types, true), 'debe incluir la tabla de items');
    assert_true(in_array('totals', $types, true), 'debe incluir el total');
    assert_true(in_array('footer', $types, true));
});

test('PresupuestoTemplate incluye datos de cliente, tabla, totales y firma', function (): void {
    $doc = (new PresupuestoTemplate())->compose(doc_pedido_payload());
    $types = doc_types($doc);
    assert_true(in_array('text', $types, true), 'bloque de datos de cliente');
    assert_true(in_array('table', $types, true));
    assert_true(in_array('totals', $types, true));
    assert_true(in_array('signature', $types, true));
});

test('ContratoTemplate compone texto largo y firma', function (): void {
    $t = new ContratoTemplate();
    assert_true($t->supports('registro'));
    $doc = $t->compose([
        'title'  => 'Contrato',
        'marca'  => ['empresa' => 'ACME'],
        'record' => ['nombre' => 'Juan Pérez', 'email' => 'j@x.com', 'telefono' => '555', 'status' => 'activo'],
        'relations' => [],
    ]);
    $types = doc_types($doc);
    assert_true(in_array('text', $types, true));
    assert_true(in_array('signature', $types, true));
});
