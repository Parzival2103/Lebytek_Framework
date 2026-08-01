<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('despliegue-y-versionado documents three-file semver sync on release', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/core/despliegue-y-versionado.md');
    assert_true(str_contains($src, 'composer.json'), 'checklist must mention composer.json version');
    assert_true(str_contains($src, 'skeleton/config/app.php'), 'checklist must mention skeleton config');
    assert_true(str_contains($src, 'PlatformVersionSemver'), 'checklist must reference PlatformVersionSemver test');
    assert_true(str_contains($src, 'config/app.php'), 'checklist must mention harness config/app.php');
});
