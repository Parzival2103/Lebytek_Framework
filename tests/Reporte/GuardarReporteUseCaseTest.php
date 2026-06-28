<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\GuardarReporteUseCase;
use Lebytek\Framework\Application\Reporte\ReporteConfigLoader;
use Lebytek\Framework\Application\Reporte\ReporteConfigValidator;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

function gru_useCase(): GuardarReporteUseCase
{
    return new GuardarReporteUseCase(new ReporteConfigLoader(new ReporteConfigValidator()));
}

function gru_input(): array
{
    return [
        'nombre' => 'Citas por estado',
        'fuente_key' => 'citas',
        'template_key' => 'tabla_estadistica',
        'columnas' => [['name' => 'estado']],
        'tratamientos' => ['group_by' => ['estado'], 'aggregations' => [['op' => 'count', 'column' => '']], 'order' => ['by' => 'estado', 'dir' => 'asc']],
        'filtros' => ['estado' => 'pagado'],
        'periodo' => ['preset' => 'mes'],
        'opciones' => ['titulo' => 'Citas por estado', 'orientacion' => 'portrait'],
        'compartido' => true,
    ];
}

test('serializa una selección válida a columnas de rep_reportes', function (): void {
    $data = gru_useCase()->toRow(gru_input(), 3);
    assert_same('citas', $data['fuente_key']);
    assert_same('coleccion', $data['modo']);
    assert_same('tabla_estadistica', $data['template_key']);
    assert_same(1, $data['compartido']);
    assert_same(3, $data['created_by']);
    $cols = json_decode($data['columnas'], true);
    assert_same('estado', $cols[0]['name']);
    $filtros = json_decode($data['filtros'], true);
    assert_same('pagado', $filtros['estado']);
});

test('rechaza una plantilla no ofrecida por la fuente', function (): void {
    $input = gru_input();
    $input['template_key'] = 'ticket_compra';
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});

test('rechaza una columna no expuesta', function (): void {
    $input = gru_input();
    $input['columnas'] = [['name' => 'secreto']];
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});

test('rechaza un preset de periodo no ofrecido por la fuente', function (): void {
    $input = gru_input();
    $input['periodo'] = ['preset' => 'decada'];
    assert_throws(ValidationException::class, fn() => gru_useCase()->toRow($input, 3));
});
