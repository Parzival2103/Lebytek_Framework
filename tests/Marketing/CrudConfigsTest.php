<?php
// tests/Marketing/CrudConfigsTest.php
declare(strict_types=1);

test('los CRUD JSON de marketing son válidos y apuntan a tablas dom_mkt_*', function (): void {
    $map = [
        'mkt_leads'      => 'dom_mkt_leads',
        'mkt_paquetes'   => 'dom_mkt_paquetes',
        'mkt_bloques'    => 'dom_mkt_bloques',
        'mkt_plantillas' => 'dom_mkt_plantillas',
        'mkt_secuencias' => 'dom_mkt_secuencias',
        'mkt_ordenes'    => 'dom_mkt_ordenes',
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

test('mkt_leads excluye pendiente y demo_baja del listado', function (): void {
    $cfg = json_decode((string) file_get_contents(ROOT_PATH . '/config/cruds/mkt_leads.json'), true);
    assert_same([
        ['field' => 'estado', 'values' => ['pendiente', 'demo_baja']],
    ], $cfg['list']['exclude']);

    $def = \Lebytek\Framework\Domain\Entities\CrudResourceDefinition::fromArray($cfg);
    assert_same([
        ['field' => 'estado', 'values' => ['pendiente', 'demo_baja']],
    ], $def->listExcludes());
});

test('mkt_leads prioriza nombre y estado en responsive', function (): void {
    $cfg = json_decode((string) file_get_contents(ROOT_PATH . '/config/cruds/mkt_leads.json'), true);
    $byName = [];
    foreach ($cfg['list']['columns'] as $col) {
        $byName[(string) $col['name']] = $col;
    }
    assert_same(2, (int) $byName['nombre']['priority']);
    assert_same(2, (int) $byName['estado']['priority']);
    assert_true((int) $byName['id']['priority'] > (int) $byName['nombre']['priority']);
    assert_true(empty($byName['api_lifecycle_status']['priority']));
    assert_true(empty($byName['api_tenant_public_id']['priority']));
});

test('mkt_leads lista columnas de churn demo', function (): void {
    $cfg = json_decode((string) file_get_contents(ROOT_PATH . '/config/cruds/mkt_leads.json'), true);
    $names = array_map(static fn (array $c): string => (string) $c['name'], $cfg['list']['columns']);
    assert_true(in_array('demo_expires_at', $names, true));
    assert_true(in_array('plan_slug', $names, true));
    assert_true(in_array('last_activity_at', $names, true));
    $detailCols = $cfg['detail']['tabs'][0]['columns'];
    assert_true(in_array('demo_expires_at', $detailCols, true));
    assert_true(in_array('first_message_sent_at', $detailCols, true));
});
