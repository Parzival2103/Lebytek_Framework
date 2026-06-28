<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Reporte\ReporteConfigValidator;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

function rcv_valid_config(): array
{
    return [
        'fuente' => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose' => [
            'columns' => [
                ['name' => 'cliente', 'label' => 'Cliente', 'type' => 'text'],
                ['name' => 'total',   'label' => 'Total',   'type' => 'money', 'treatments' => ['sum']],
            ],
            'group_by' => ['cliente'],
            'order_by' => ['total'],
            'filters'  => [['field' => 'estado', 'label' => 'Estado']],
            'period'   => ['field' => 'fecha', 'presets' => ['mes', 'todo']],
            'max_rows' => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica']],
    ];
}

$columns = ['cliente', 'total', 'estado', 'fecha'];

test('config válida no lanza', function () use ($columns): void {
    (new ReporteConfigValidator())->validate(rcv_valid_config(), $columns);
    assert_true(true);
});

test('columna inexistente lanza ValidationException', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][] = ['name' => 'fantasma', 'type' => 'text'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('columna protegida lanza ValidationException', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][] = ['name' => 'created_by', 'type' => 'number'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, array_merge($columns, ['created_by'])));
});

test('tratamiento numérico sobre columna de texto lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['columns'][0]['treatments'] = ['sum'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('preset de periodo no soportado lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    $cfg['expose']['period']['presets'] = ['decada'];
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('max_rows ausente lanza', function () use ($columns): void {
    $cfg = rcv_valid_config();
    unset($cfg['expose']['max_rows']);
    assert_throws(ValidationException::class, fn() => (new ReporteConfigValidator())->validate($cfg, $columns));
});

test('acepta expose.relations que existen en el recurso', function (): void {
    $config = [
        'fuente'  => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'type' => 'text']],
            'relations' => ['cliente', 'items'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => ['ticket_compra']],
    ];
    (new ReporteConfigValidator())
        ->validate($config, ['id', 'folio'], ['cliente', 'items']);
    assert_true(true);
});

test('rechaza expose.relations inexistentes en el recurso', function (): void {
    $config = [
        'fuente'  => ['key' => 'pedidos', 'resource' => 'demo_pedidos'],
        'expose'  => [
            'columns'   => [['name' => 'folio', 'type' => 'text']],
            'relations' => ['fantasma'],
            'max_rows'  => 5000,
        ],
        'templates' => ['coleccion' => ['tabla_estadistica'], 'registro' => []],
    ];
    assert_throws(ValidationException::class, fn() =>
        (new ReporteConfigValidator())
            ->validate($config, ['id', 'folio'], ['cliente', 'items']));
});
