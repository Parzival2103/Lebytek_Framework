<?php
declare(strict_types=1);

test('config invoicing define provider facturapi', function (): void {
    $cfg = require ROOT_PATH . '/config/invoicing.php';
    assert_true(isset($cfg['providers']['facturapi']));
    assert_same('facturapi', $cfg['providers']['facturapi']['driver']);
    assert_true(($cfg['providers']['facturapi']['enabled'] ?? true) === false, 'facturapi provider must be OFF by default');
});

test('vertical deja invoicing OFF en harness y skeleton', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(($vertical['modules']['invoicing'] ?? true) === false);
    $skel = require ROOT_PATH . '/skeleton/config/vertical.php';
    assert_true(($skel['modules']['invoicing'] ?? true) === false);
});

test('module manifest invoicing bootstrap_sql apunta a invoicing.sql', function (): void {
    $mod = require ROOT_PATH . '/config/modules/invoicing.php';
    assert_same('invoicing', $mod['clave'] ?? null);
    assert_same('database/schema/modules/invoicing.sql', $mod['bootstrap_sql'] ?? null);
    $skel = require ROOT_PATH . '/skeleton/config/modules/invoicing.php';
    assert_same('database/schema/modules/invoicing.sql', $skel['bootstrap_sql'] ?? null);
});

test('module manifest invoicing declara permisos RBAC de operaciones', function (): void {
    $expected = [
        'invoicing.emitir',
        'invoicing.cancelar',
        'invoicing.descargar',
        'invoicing.enviar',
        'invoicing.reconciliar',
    ];
    $mod = require ROOT_PATH . '/config/modules/invoicing.php';
    assert_same($expected, $mod['permisos'] ?? null);
    $skel = require ROOT_PATH . '/skeleton/config/modules/invoicing.php';
    assert_same($expected, $skel['permisos'] ?? null);
});

test('composer exige PHP >=8.2 y facturapi/facturapi-php', function (): void {
    $composer = json_decode((string) file_get_contents(ROOT_PATH . '/composer.json'), true);
    assert_same('>=8.2', $composer['require']['php'] ?? null);
    assert_true(isset($composer['require']['facturapi/facturapi-php']));
    $skel = json_decode((string) file_get_contents(ROOT_PATH . '/skeleton/composer.json'), true);
    assert_same('>=8.2', $skel['require']['php'] ?? null);
});

test('spec header apunta a enmiendas del plan A1-A10', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md');
    assert_true(str_contains($src, 'Plan amendments A1'));
    assert_true(str_contains($src, 'supersede this spec where they disagree'));
    assert_true(str_contains($src, 'docs/superpowers/plans/2026-08-07-invoicing-facturapi.md'));
});
