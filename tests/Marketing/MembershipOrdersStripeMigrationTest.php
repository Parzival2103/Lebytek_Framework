<?php
declare(strict_types=1);

test('migración stripe añade columnas de pago a dom_mkt_ordenes', function (): void {
    $sql = (string) file_get_contents(
        ROOT_PATH . '/database/migrations/20260715120000_mkt_ordenes_stripe.sql'
    );
    assert_true(str_contains($sql, 'metodo_pago'));
    assert_true(str_contains($sql, 'payment_provider'));
    assert_true(str_contains($sql, 'payment_ref'));
});

test('marketing module lista la migración stripe', function (): void {
    $manifest = require ROOT_PATH . '/config/modules/marketing.php';
    assert_true(in_array('20260715120000_mkt_ordenes_stripe.sql', $manifest['migraciones'], true));
});
