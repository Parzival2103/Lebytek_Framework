<?php
// tests/Marketing/ManifestTest.php
declare(strict_types=1);

use Lebytek\Framework\Application\Install\ModuleRegistry;

test('marketing manifiesto se carga con identidad y dependencias correctas', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    assert_true($m !== null, 'manifiesto marketing existe');
    assert_same('marketing', $m->clave);
    assert_same('1.0.0', $m->version);
    assert_true(in_array('core', $m->requiere, true), 'requiere core');
    assert_true(in_array('crud-engine', $m->requiere, true), 'requiere crud-engine');
});

test('marketing manifiesto declara bootstrap_sql existente', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    assert_same('database/schema/modules/marketing.sql', $m->bootstrapSql);
});

test('marketing manifiesto declara los CRUDs de contenido', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $m = $registry->get('marketing');
    foreach (['mkt_leads','mkt_paquetes','mkt_bloques','mkt_plantillas','mkt_secuencias'] as $crud) {
        assert_true(in_array($crud, $m->cruds, true), "declara crud {$crud}");
    }
});

test('toggle marketing existe y por defecto está apagado', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(array_key_exists('marketing', $vertical['modules']), 'toggle declarado');
    assert_same(false, $vertical['modules']['marketing'], 'apagado por defecto');
});
