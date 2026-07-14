<?php
// tests/Marketing/CrudConfigsTest.php
declare(strict_types=1);

test('los 5 CRUD JSON de marketing son válidos y apuntan a tablas dom_mkt_*', function (): void {
    $map = [
        'mkt_leads'      => 'dom_mkt_leads',
        'mkt_paquetes'   => 'dom_mkt_paquetes',
        'mkt_bloques'    => 'dom_mkt_bloques',
        'mkt_plantillas' => 'dom_mkt_plantillas',
        'mkt_secuencias' => 'dom_mkt_secuencias',
    ];
    foreach ($map as $key => $tabla) {
        $path = ROOT_PATH . "/config/cruds/{$key}.json";
        assert_true(is_file($path), "{$key}.json existe");
        $cfg = json_decode((string) file_get_contents($path), true);
        assert_true(is_array($cfg), "{$key}.json es JSON válido");
        assert_same($key, $cfg['resource']['key']);
        assert_same($tabla, $cfg['resource']['table']);
        assert_same('marketing', $cfg['resource']['permission_prefix']);
    }
});

test('mkt_leads oculta pendiente y demo_baja vía scope_handler', function (): void {
    $cfg = json_decode((string) file_get_contents(ROOT_PATH . '/config/cruds/mkt_leads.json'), true);
    assert_same('mkt_leads_active', $cfg['list']['scope_handler']);

    $handlers = require ROOT_PATH . '/config/crud_handlers.php';
    assert_true(isset($handlers['mkt_leads_active']), 'handler registrado');
    assert_same(
        \App\Application\Marketing\MktLeadsActiveListScope::class,
        $handlers['mkt_leads_active']
    );
});
