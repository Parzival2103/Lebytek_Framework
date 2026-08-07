<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/**
 * El zip/dist de Composer (git archive / GitHub) debe omitir ruido interno
 * del mantenedor. Los consumidores no necesitan audits, automation ni archive.
 */
test('gitattributes export-ignores maintainer-only docs and tests', function () use ($root): void {
    $path = $root . '/.gitattributes';
    assert_true(is_readable($path), 'missing .gitattributes');
    $src = (string) file_get_contents($path);

    $required = [
        'docs/superpowers/',
        'docs/audits/',
        'docs/automation/',
        'docs/automation-reports/',
        'docs/archive/',
        'tests/',
    ];

    foreach ($required as $path) {
        $quoted = preg_quote($path, '/');
        assert_true(
            (bool) preg_match('/^' . $quoted . '\s+export-ignore\s*$/m', $src),
            ".gitattributes must export-ignore «{$path}» so Composer dist omits maintainer noise"
        );
    }

    // Guías de uso del paquete deben seguir viajando en el dist.
    assert_true(
        !preg_match('/^docs\/core\/\s+export-ignore/m', $src),
        'docs/core/ must remain in Composer dist (usage handbook)'
    );
    assert_true(
        !preg_match('/^docs\/modules\/\s+export-ignore/m', $src),
        'docs/modules/ must remain in Composer dist (usage handbook)'
    );
});
