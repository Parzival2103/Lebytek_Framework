<?php
// tests/Marketing/SchemaBootstrapTest.php
declare(strict_types=1);

test('marketing.sql crea todas las tablas dom_mkt_* de forma idempotente', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true($sql !== false, 'archivo existe');
    foreach ([
        'dom_mkt_leads', 'dom_mkt_provisiones', 'dom_mkt_paquetes',
        'dom_mkt_bloques', 'dom_mkt_plantillas', 'dom_mkt_secuencias', 'dom_mkt_paginas',
        'dom_mkt_ordenes',
        'dom_mkt_variant_weights', 'dom_mkt_variant_proposals',
        'dom_mkt_landing_sessions', 'dom_mkt_landing_events',
    ] as $tabla) {
        assert_true(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{$tabla}`"), "crea {$tabla}");
    }
});

test('marketing.sql incluye columnas de pago Stripe en dom_mkt_ordenes', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, '`metodo_pago`'), 'columna metodo_pago');
    assert_true(str_contains($sql, '`payment_provider`'), 'columna payment_provider');
    assert_true(str_contains($sql, '`payment_ref`'), 'columna payment_ref');
    assert_true(str_contains($sql, '`idx_mkt_ordenes_payment_ref`'), 'índice payment_ref');
});

test('marketing.sql inserta permisos y menú con INSERT IGNORE', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "'marketing.ver'"), 'permiso ver');
    assert_true(str_contains($sql, "'marketing.gestionar'"), 'permiso gestionar');
    assert_true(str_contains($sql, "'marketing.leads'"), 'permiso leads');
    assert_true(str_contains($sql, "'marketing.ordenes'"), 'permiso ordenes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'permisos idempotentes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `core_menu_items`'), 'menú idempotente');
    assert_true(str_contains($sql, "'marketing'"), 'menú padre marketing');
    assert_true(str_contains($sql, '/admin/crud/mkt_ordenes'), 'menú órdenes');
});

test('marketing.sql siembra paquetes comerciales con slug y precios VPS', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "`slug`"), 'columna slug en paquetes');
    assert_true(str_contains($sql, "`mensajes_mes_limite`"), 'columna mensajes_mes_limite');
    assert_true(str_contains($sql, "'starter'"), 'slug starter');
    assert_true(str_contains($sql, "'business'"), 'slug business');
    assert_true(str_contains($sql, "'empresa'"), 'slug empresa');
    assert_true(str_contains($sql, '2199.00'), 'precio Starter comercial');
    assert_true(str_contains($sql, '4499.00'), 'precio Business comercial');
    assert_true(str_contains($sql, '5000 AS mensajes_mes_limite'), 'límite Starter comercial');
    assert_true(str_contains($sql, '80000 AS mensajes_mes_limite'), 'límite Business comercial');
    assert_true(preg_match('/(?<!\d)499\.00(?!\d)/', $sql) !== 1, 'precio seed viejo Starter eliminado');
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
