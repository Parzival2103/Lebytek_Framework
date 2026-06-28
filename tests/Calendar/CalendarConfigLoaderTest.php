<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Domain\Entities\CalendarDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

test('CalendarConfigLoader carga el calendario demo_citas y resuelve su definición', function (): void {
    $loader = new CalendarConfigLoader(new CalendarConfigValidator());
    $def = $loader->load('demo_citas');
    assert_true($def instanceof CalendarDefinition, 'devuelve CalendarDefinition');
    assert_same('demo_citas', $def->key(), 'key del archivo coincide');
    assert_same('demo_citas', $def->resource(), 'apunta al recurso CRUD');
});

test('CalendarConfigLoader lanza si el calendario no existe', function (): void {
    $loader = new CalendarConfigLoader(new CalendarConfigValidator());
    assert_throws(ValidationException::class, fn() => $loader->load('no_existe_calendario'));
});

test('CalendarConfigLoader::listCalendars incluye demo_citas', function (): void {
    $loader = new CalendarConfigLoader(new CalendarConfigValidator());
    $list = $loader->listCalendars();
    assert_same('Agenda de Citas', $list['demo_citas'] ?? null, 'lista el calendario demo');
});
