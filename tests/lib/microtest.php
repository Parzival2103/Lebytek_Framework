<?php

declare(strict_types=1);

$GLOBALS['__mt'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__mt']['pass']++;
        fwrite(STDOUT, "  PASS  {$name}\n");
    } catch (\Throwable $e) {
        $GLOBALS['__mt']['fail']++;
        $GLOBALS['__mt']['failures'][] = $name . ' :: ' . $e->getMessage();
        fwrite(STDOUT, "  FAIL  {$name}  --  " . $e->getMessage() . "\n");
    }
}

function assert_true(bool $cond, string $msg = 'expected true'): void
{
    if (!$cond) {
        throw new \RuntimeException($msg);
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $prefix = $msg !== '' ? $msg . ': ' : '';
        throw new \RuntimeException($prefix . 'expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

function assert_null(mixed $actual, string $msg = 'expected null'): void
{
    if ($actual !== null) {
        throw new \RuntimeException($msg . ' got ' . var_export($actual, true));
    }
}

function assert_throws(string $exceptionClass, callable $fn, string $msg = ''): void
{
    $prefix = $msg !== '' ? $msg . ': ' : '';
    try {
        $fn();
    } catch (\Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new \RuntimeException($prefix . "expected {$exceptionClass} got " . get_class($e) . ' (' . $e->getMessage() . ')');
        }
        return;
    }
    throw new \RuntimeException($prefix . "expected {$exceptionClass} to be thrown, nothing thrown");
}

function microtest_summary(): void
{
    $mt = $GLOBALS['__mt'];
    fwrite(STDOUT, "\n{$mt['pass']} passed, {$mt['fail']} failed\n");
    exit($mt['fail'] > 0 ? 1 : 0);
}
