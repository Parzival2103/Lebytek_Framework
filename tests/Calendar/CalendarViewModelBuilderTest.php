<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Services\CalendarConfigLoader;
use Lebytek\Framework\Application\Services\CalendarConfigValidator;
use Lebytek\Framework\Application\Services\CalendarViewModelBuilder;
use Lebytek\Framework\Application\Services\RbacService;
use Lebytek\Framework\Domain\Exceptions\AccesoException;

function vmb_builder(): CalendarViewModelBuilder
{
    return new CalendarViewModelBuilder(
        new CalendarConfigLoader(new CalendarConfigValidator()),
        new RbacService()
    );
}

test('CalendarViewModelBuilder arma el shell con capacidades según permisos', function (): void {
    $_SESSION['auth_permisos'] = ['demo_citas.ver', 'demo_citas.crear', 'demo_citas.editar', 'demo_citas.eliminar'];
    $_SESSION['auth_roles'] = [];

    $data = vmb_builder()->build('demo_citas');

    assert_same('Agenda de Citas', $data['title'], 'título');
    assert_same('demo_citas', $data['resource'], 'recurso');
    assert_same('/admin/calendario/demo_citas/eventos', $data['feedUrl'], 'feed url');
    assert_same('/admin/crud/demo_citas', $data['crudBaseUrl'], 'crud base url');
    assert_same('month', $data['views']['default'], 'vista por defecto');
    assert_same(['month', 'week', 'day', 'table'], $data['views']['enabled'], 'vistas habilitadas');
    assert_same(true, $data['capabilities']['canCreate'], 'puede crear');
    assert_same(true, $data['capabilities']['canEdit'], 'puede editar');
    assert_same(true, $data['capabilities']['canDelete'], 'puede eliminar');
    assert_true(count($data['legend']) >= 1, 'leyenda no vacía');
});

test('CalendarViewModelBuilder recorta capacidades sin permisos de escritura', function (): void {
    $_SESSION['auth_permisos'] = ['demo_citas.ver'];
    $_SESSION['auth_roles'] = [];

    $data = vmb_builder()->build('demo_citas');

    assert_same(false, $data['capabilities']['canCreate'], 'sin crear');
    assert_same(false, $data['capabilities']['canEdit'], 'sin editar');
    assert_same(false, $data['capabilities']['canDelete'], 'sin eliminar');
});

test('CalendarViewModelBuilder lanza AccesoException sin permiso de lectura', function (): void {
    $_SESSION['auth_permisos'] = [];
    $_SESSION['auth_roles'] = [];

    assert_throws(AccesoException::class, fn() => vmb_builder()->build('demo_citas'));
});
