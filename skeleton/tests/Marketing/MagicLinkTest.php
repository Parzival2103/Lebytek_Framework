<?php
// tests/Marketing/MagicLinkTest.php
declare(strict_types=1);

use App\Domain\Marketing\ValueObjects\MagicLinkToken;

test('MagicLinkToken genera 64 hex', function (): void {
    $t = MagicLinkToken::generar();
    assert_same(64, strlen($t->valor()));
    assert_same(1, preg_match('/^[0-9a-f]{64}$/', $t->valor()));
});

test('dos tokens generados difieren', function (): void {
    assert_true(MagicLinkToken::generar()->valor() !== MagicLinkToken::generar()->valor());
});

test('esFormatoValido distingue tokens bien formados', function (): void {
    assert_same(true, MagicLinkToken::esFormatoValido(str_repeat('a', 64)));
    assert_same(false, MagicLinkToken::esFormatoValido('corto'));
    assert_same(false, MagicLinkToken::esFormatoValido(str_repeat('Z', 64)));
});
