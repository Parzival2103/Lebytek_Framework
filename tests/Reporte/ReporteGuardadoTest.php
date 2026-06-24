<?php
declare(strict_types=1);

use App\Domain\Reporte\ReporteGuardado;

function rg_row(): array
{
    return [
        'id' => 7,
        'clave' => 'citas_demo',
        'nombre' => 'Citas por estado',
        'fuente_key' => 'citas',
        'modo' => 'coleccion',
        'columnas' => json_encode([['name' => 'estado', 'label' => 'Estado', 'type' => 'text']]),
        'tratamientos' => json_encode(['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']]),
        'filtros' => json_encode([]),
        'periodo' => json_encode(['preset' => 'mes']),
        'opciones' => json_encode(['titulo' => 'Citas por estado', 'orientacion' => 'portrait']),
        'template_key' => 'tabla_estadistica',
        'compartido' => 1,
        'created_by' => 3,
    ];
}

test('hidrata desde una fila decodificando JSON', function (): void {
    $r = ReporteGuardado::fromRow(rg_row());
    assert_same(7, $r->id());
    assert_same('citas', $r->fuenteKey());
    assert_same('coleccion', $r->modo());
    assert_same('estado', $r->columnas()[0]['name']);
    assert_same(['estado'], $r->tratamientos()['group_by']);
    assert_same('mes', $r->periodo()['preset']);
    assert_same('Citas por estado', $r->opciones()['titulo']);
    assert_same('tabla_estadistica', $r->templateKey());
    assert_true($r->compartido());
    assert_same(3, $r->createdBy());
});

test('JSON inválido degrada a array vacío sin romper', function (): void {
    $row = rg_row();
    $row['filtros'] = 'no-json';
    $r = ReporteGuardado::fromRow($row);
    assert_same([], $r->filtros());
});
