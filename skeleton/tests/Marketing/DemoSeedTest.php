<?php
// tests/Marketing/DemoSeedTest.php
declare(strict_types=1);

test('marketing_demo.sql existe y es idempotente sin FKs ni dominio acoplado', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true($sql !== false, 'archivo existe');
    assert_true(str_contains($sql, 'WHERE NOT EXISTS'), 'inserts guardados por NOT EXISTS');
    assert_true(!str_contains($sql, 'FOREIGN KEY'), 'sin FOREIGN KEY');
    assert_true(!str_contains($sql, 'CREATE TABLE'), 'no recrea tablas (solo datos)');
    assert_true(str_contains($sql, 'dom_mkt_paquetes'), 'opera sobre dom_mkt_paquetes');
});

test('marketing_demo.sql siembra los 3 paquetes demo y desactiva el placeholder', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true(str_contains($sql, "'Básico'"), 'plan Básico');
    assert_true(str_contains($sql, "'Pro'"), 'plan Pro');
    assert_true(str_contains($sql, "'Empresa'"), 'plan Empresa');
    assert_true(str_contains($sql, "'Más popular'"), 'badge del destacado');
    assert_true(str_contains($sql, "SET `activo` = 0 WHERE `nombre` = 'Plan Demo'"), 'desactiva placeholder genérico');
});

test('marketing_demo.sql siembra bloques hero/trust/testimonios/footer', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true(str_contains($sql, "'hero'"), 'bloque hero');
    assert_true(str_contains($sql, "'trust'"), 'bloque trust');
    assert_true(str_contains($sql, "'testimonios'"), 'bloque testimonios');
    assert_true(str_contains($sql, "'footer'"), 'bloque footer');
    assert_true(str_contains($sql, '/assets/publico/hero-mock.jpg'), 'media del hero (imagen genérica)');
});

test('seed.php aplica el demo de marketing tras el flag --marketing-demo', function (): void {
    $src = file_get_contents(ROOT_PATH . '/scripts/seed.php');
    assert_true($src !== false, 'seed.php existe');
    assert_true(str_contains($src, '--marketing-demo'), 'declara el flag');
    assert_true(str_contains($src, 'marketing_demo.sql'), 'referencia el archivo demo');
});
