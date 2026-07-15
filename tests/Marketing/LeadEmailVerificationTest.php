<?php
declare(strict_types=1);

use App\Domain\Marketing\LeadEmailVerification;

if (!function_exists('assert_false')) {
    function assert_false(bool $cond, string $msg = 'expected false'): void
    {
        assert_true(!$cond, $msg);
    }
}

test('genera codigo de 6 chars del alfabeto seguro', function (): void {
    $code = LeadEmailVerification::generateCode();
    assert_same(6, strlen($code));
    assert_true((bool) preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code));
});

test('genera token hex de 64 chars', function (): void {
    $token = LeadEmailVerification::generateToken();
    assert_same(64, strlen($token));
    assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $token));
});

test('hash y verify usan comparacion segura', function (): void {
    $hash = LeadEmailVerification::hashCode('AB12CD');
    assert_true(LeadEmailVerification::codeMatches('AB12CD', $hash));
    assert_true(LeadEmailVerification::codeMatches('ab12cd', $hash)); // case-insensitive
    assert_false(LeadEmailVerification::codeMatches('ZZZZZZ', $hash));
});

test('constantes de politica', function (): void {
    assert_same(24, LeadEmailVerification::TTL_HOURS);
    assert_same(5, LeadEmailVerification::MAX_ATTEMPTS);
});
