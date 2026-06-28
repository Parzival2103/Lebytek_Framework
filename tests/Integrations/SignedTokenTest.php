<?php
// tests/Integrations/SignedTokenTest.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Security\SignedToken;

test('SignedToken round-trip devuelve el accountId', function () {
    $t = SignedToken::make(42, 3600);
    assert_same(42, SignedToken::verify($t));
});

test('SignedToken rechaza firma manipulada', function () {
    $t = SignedToken::make(42, 3600);
    assert_null(SignedToken::verify($t . 'x'));
});

test('SignedToken rechaza token expirado', function () {
    $t = SignedToken::make(42, -1);
    assert_null(SignedToken::verify($t));
});
