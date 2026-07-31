<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('operational runbooks must not reference frozen legacy branch or removed deploy scripts (M8)', function () use ($root): void {
    $operationalPaths = [
        'docs/composer-setup.md',
        'docs/integration/VPS_CHECKLIST.md',
        'docs/integration/lebytek-implementation-real.md',
        'docs/integration/role-delegation-lebytek-api.md',
    ];
    $forbidden = [
        'dev-feature/backoffice-api-integration' => 'replace with semver constraint ^1.2 in composer.json require',
        'feature/backoffice-api-integration' => 'replace with Lebytek_Portal @ main — see docs/ENVIRONMENTS.md',
        'vps-deploy-lebytek-com.sh' => 'script removed PR #36 — use Portal git pull',
        'vps-deploy-waapi.sh' => 'script removed PR #36 — use Portal git pull',
        'vps-deploy-skeleton.sh' => 'script removed PR #36 — use publish-skeleton.sh + create-project',
    ];

    foreach ($operationalPaths as $rel) {
        $path = $root . '/' . $rel;
        assert_true(is_file($path), "Operational runbook missing: {$rel}");
        $src = (string) file_get_contents($path);
        foreach ($forbidden as $needle => $action) {
            assert_true(
                !str_contains($src, $needle),
                "{$rel} must not reference «{$needle}». Action: {$action}. Canonical map: docs/ENVIRONMENTS.md"
            );
        }
    }
});
