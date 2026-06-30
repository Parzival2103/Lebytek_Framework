<?php
// tests/Marketing/SchemaBootstrapTest.php
declare(strict_types=1);

test('marketing.sql crea todas las tablas dom_mkt_* de forma idempotente', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true($sql !== false, 'archivo existe');
    foreach ([
        'dom_mkt_leads', 'dom_mkt_provisiones', 'dom_mkt_paquetes',
        'dom_mkt_bloques', 'dom_mkt_plantillas', 'dom_mkt_secuencias', 'dom_mkt_paginas',
    ] as $tabla) {
        assert_true(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{$tabla}`"), "crea {$tabla}");
    }
});

test('marketing.sql inserta permisos y menú con INSERT IGNORE', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "'marketing.ver'"), 'permiso ver');
    assert_true(str_contains($sql, "'marketing.gestionar'"), 'permiso gestionar');
    assert_true(str_contains($sql, "'marketing.leads'"), 'permiso leads');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'permisos idempotentes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `core_menu_items`'), 'menú idempotente');
    assert_true(str_contains($sql, "'marketing'"), 'menú padre marketing');
});

test('marketing.sql siembra demo guardada por NOT EXISTS', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, 'NOT EXISTS'), 'demo idempotente');
    assert_true(str_contains($sql, 'access_token'), 'columna magic-link presente');
    assert_true(str_contains($sql, '`payload`'), 'columna payload JSON presente');
});

test('marketing.sql no define FKs cross-módulo', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(!str_contains($sql, 'FOREIGN KEY'), 'sin FOREIGN KEY declaradas');
});
