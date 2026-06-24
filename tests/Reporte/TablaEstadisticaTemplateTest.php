<?php
declare(strict_types=1);

use App\Application\Pdf\Templates\TablaEstadisticaTemplate;
use App\Domain\Pdf\PdfDocument;
use App\Domain\Reporte\ReporteTemplateInterface;

function tet_payload(): array
{
    return [
        'title' => 'Citas por estado',
        'period' => 'Este mes',
        'orientation' => 'portrait',
        'columns' => [
            ['name' => 'estado', 'label' => 'Estado', 'format' => 'raw'],
            ['name' => 'count', 'label' => 'Cantidad', 'format' => 'number'],
        ],
        'rows' => [
            ['estado' => 'pagado', 'count' => 2],
            ['estado' => 'pendiente', 'count' => 1],
        ],
        'totals' => [['label' => 'Cantidad', 'value' => 3, 'format' => 'number']],
        'marca' => ['empresa' => 'Demo S.A.', 'logo' => ''],
    ];
}

test('es una ReporteTemplateInterface y soporta colección', function (): void {
    $t = new TablaEstadisticaTemplate();
    assert_true($t instanceof ReporteTemplateInterface);
    assert_true($t->supports('coleccion'));
    assert_true(!$t->supports('registro'));
});

test('el schema de pasos pide periodo y tratamientos', function (): void {
    $s = (new TablaEstadisticaTemplate())->schemaPasos();
    assert_same('coleccion', $s['modo']);
    assert_true($s['requiere_periodo']);
    assert_true($s['permite_tratamientos']);
});

test('compose devuelve un PdfDocument con header, tabla y totales', function (): void {
    $doc = (new TablaEstadisticaTemplate())->compose(tet_payload());
    assert_true($doc instanceof PdfDocument);
    $types = array_map(static fn($b) => $b->type(), $doc->blocks());
    assert_true(in_array('header', $types, true), 'tiene header');
    assert_true(in_array('table', $types, true), 'tiene tabla');
    assert_true(in_array('totals', $types, true), 'tiene totales');
});
