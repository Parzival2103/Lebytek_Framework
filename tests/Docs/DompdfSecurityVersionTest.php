<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('composer.lock pins dompdf/dompdf at a secure patch level', function () use ($root): void {
    $lockPath = $root . '/composer.lock';
    assert_true(is_readable($lockPath), 'composer.lock must exist');
    $lock = json_decode((string) file_get_contents($lockPath), true);
    assert_true(is_array($lock), 'composer.lock must be valid JSON');

    $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    $dompdf = null;
    foreach ($packages as $pkg) {
        if (($pkg['name'] ?? '') === 'dompdf/dompdf') {
            $dompdf = $pkg;
            break;
        }
    }

    assert_true($dompdf !== null, 'composer.lock must contain dompdf/dompdf');
    $version = ltrim((string) ($dompdf['version'] ?? ''), 'v');
    assert_true(
        version_compare($version, '3.1.6', '>='),
        "dompdf/dompdf must be >= 3.1.6 (found {$version}). Action: composer update dompdf/dompdf (M9)"
    );
});
