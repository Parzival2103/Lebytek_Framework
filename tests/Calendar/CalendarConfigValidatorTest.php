<?php
declare(strict_types=1);

use App\Application\Services\CalendarConfigValidator;
use App\Domain\Exceptions\ValidationException;

function cv_cols(): array { return ['id', 'cliente', 'servicio', 'estado', 'fecha_inicio', 'fecha_fin']; }

function cv_valid(): array
{
    return [
        'calendar' => ['key' => 'citas', 'title' => 'Agenda', 'resource' => 'demo_citas'],
        'mapping' => ['start' => 'fecha_inicio', 'end' => 'fecha_fin', 'title' => '{cliente}',
                      'color' => ['by' => 'estado', 'map' => ['pendiente' => 'warning']]],
        'views' => ['default' => 'month', 'enabled' => ['month', 'table']],
    ];
}

test('CalendarConfigValidator acepta config válida', function (): void {
    (new CalendarConfigValidator())->validate(cv_valid(), cv_cols());
    assert_true(true, 'no lanzó excepción');
});

test('CalendarConfigValidator rechaza columna start inexistente', function (): void {
    $cfg = cv_valid();
    $cfg['mapping']['start'] = 'no_existe';
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza vista default fuera de enabled', function (): void {
    $cfg = cv_valid();
    $cfg['views']['default'] = 'week';
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza vista no soportada', function (): void {
    $cfg = cv_valid();
    $cfg['views']['enabled'] = ['month', 'galaxia'];
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});

test('CalendarConfigValidator rechaza color.by=field sin field válido', function (): void {
    $cfg = cv_valid();
    $cfg['mapping']['color'] = ['by' => 'field', 'field' => 'no_existe'];
    assert_throws(ValidationException::class, fn() => (new CalendarConfigValidator())->validate($cfg, cv_cols()));
});
