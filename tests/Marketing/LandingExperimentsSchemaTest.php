<?php
// tests/Marketing/LandingExperimentsSchemaTest.php
declare(strict_types=1);

test('migracion landing experiments crea tablas y columnas de atribucion', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/migrations/20260715120000_mkt_landing_experiments.sql');
    foreach ([
        'dom_mkt_variant_weights',
        'dom_mkt_variant_proposals',
        'dom_mkt_landing_sessions',
        'dom_mkt_landing_events',
    ] as $t) {
        assert_true(str_contains($sql, $t), "menciona {$t}");
    }
    assert_true(str_contains($sql, 'landing_variant'), 'columna lead landing_variant');
    assert_true(str_contains($sql, 'visitor_id'), 'columna lead visitor_id');
    assert_true(str_contains($sql, "'marketing.experimentos'"), 'permiso experimentos');
    assert_true(str_contains($sql, '/admin/marketing/experimentos'), 'menu path');
});

test('marketing.sql bootstrap incluye tablas de experimentos', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_variant_weights`'), 'weights');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_variant_proposals`'), 'proposals');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_landing_sessions`'), 'sessions');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_landing_events`'), 'events');
    assert_true(str_contains($sql, '`landing_variant`'), 'lead column');
    assert_true(str_contains($sql, "'marketing.experimentos'"), 'permiso');
});
