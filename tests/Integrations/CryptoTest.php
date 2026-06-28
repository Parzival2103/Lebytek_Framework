<?php
// tests/Integrations/CryptoTest.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Security\Crypto;

test('Crypto round-trip devuelve el texto original', function () {
    $plain = 'apiTokenInstance-1234567890abcdef';
    $cipher = Crypto::encrypt($plain);
    assert_true($cipher !== $plain, 'el cifrado no debe ser igual al claro');
    assert_same($plain, Crypto::decrypt($cipher));
});

test('Crypto produce cifrados distintos por IV aleatorio', function () {
    assert_true(Crypto::encrypt('x') !== Crypto::encrypt('x'), 'IV debe variar');
});

test('Crypto::decrypt lanza con payload corrupto', function () {
    assert_throws(\RuntimeException::class, fn() => Crypto::decrypt('no-es-base64-valido!!'));
});
