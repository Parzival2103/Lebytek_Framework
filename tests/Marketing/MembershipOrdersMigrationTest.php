<?php

declare(strict_types=1);

test('membership orders migration uses slug not clave for auth_permisos', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH.'/database/migrations/20260714200000_mkt_membership_orders.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_ordenes`'));
    assert_true(str_contains($sql, "'marketing.ordenes'"));
    assert_true(! str_contains($sql, '`clave`'), 'auth_permisos insert must use slug');
});

test('repair migration seeds marketing.ordenes with slug column', function (): void {
    $path = ROOT_PATH.'/database/migrations/20260715100000_mkt_ordenes_permission_slug.sql';
    assert_true(is_file($path), 'repair migration exists');
    $sql = (string) file_get_contents($path);
    assert_true(str_contains($sql, "'marketing.ordenes'"));
    assert_true(str_contains($sql, '`slug`'));
    assert_true(! str_contains($sql, '`clave`'), 'must not use obsolete clave column');
});

test('marketing module lists membership permission repair migration', function (): void {
    $manifest = require ROOT_PATH.'/config/modules/marketing.php';
    assert_true(in_array('20260715100000_mkt_ordenes_permission_slug.sql', $manifest['migraciones'], true));
});
