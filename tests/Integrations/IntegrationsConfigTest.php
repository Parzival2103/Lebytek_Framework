<?php
// tests/Integrations/IntegrationsConfigTest.php
declare(strict_types=1);

test('config/integrations.php define canales whatsapp y email con clases existentes', function (): void {
    $cfg = require SKELETON_PATH . '/config/integrations.php';
    assert_true(isset($cfg['channels']['whatsapp']), 'canal whatsapp definido');
    assert_true(isset($cfg['channels']['email']), 'canal email definido');
    assert_same('green_api', $cfg['channels']['whatsapp']['driver'], 'driver whatsapp');
    assert_same('mailer_adapter', $cfg['channels']['email']['driver'], 'driver email');
    assert_true(class_exists($cfg['channels']['whatsapp']['class']), 'clase whatsapp existe');
    assert_true(class_exists($cfg['channels']['email']['class']), 'clase email existe');
    assert_true(isset($cfg['rate_limit']['whatsapp']['max']), 'rate-limit whatsapp definido');
});

test('config/modules/integrations.php es un manifiesto válido', function (): void {
    $m = require SKELETON_PATH . '/config/modules/integrations.php';
    assert_same('integrations', $m['clave'], 'clave del módulo');
    assert_true($m['obligatorio'] === false, 'módulo opcional');
    assert_same('database/schema/modules/integrations.sql', $m['bootstrap_sql'], 'apunta a su SQL');
    assert_true(in_array('integrations.enviar', $m['permisos'], true), 'declara el permiso enviar');
    assert_same([], $m['cruds'], 'no expone CRUDs (tablas int_* fuera del Engine)');
});
