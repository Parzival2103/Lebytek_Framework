<?php
declare(strict_types=1);

test('config payments define gateway stripe', function (): void {
    $cfg = require ROOT_PATH . '/config/payments.php';
    assert_true(isset($cfg['gateways']['stripe']));
    assert_same('stripe', $cfg['gateways']['stripe']['driver']);
});

test('vertical skeleton deja payments OFF', function (): void {
    $vertical = require ROOT_PATH . '/config/vertical.php';
    assert_true(($vertical['modules']['payments'] ?? true) === false);
    $skel = require ROOT_PATH . '/skeleton/config/vertical.php';
    assert_true(($skel['modules']['payments'] ?? true) === false);
});

test('container gatea payments por vertical flag', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true(str_contains($src, 'vertical.modules.payments'));
    assert_true(str_contains($src, 'PaymentGatewayRegistry'));
});
