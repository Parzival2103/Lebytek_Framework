<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$workflowPath = $root . '/.github/workflows/platform-tests.yml';

test('platform CI workflow file exists at .github/workflows/platform-tests.yml', function () use ($workflowPath): void {
    assert_true(
        is_readable($workflowPath),
        'missing .github/workflows/platform-tests.yml — add workflow per spec D7 '
        . '(docs/superpowers/specs/2026-08-04-audit-platform-ci-gates-design.md)'
    );
});

test('platform-tests.yml references php tests/run.php and fast suites', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'php tests/run.php'), 'workflow must run php tests/run.php');
    assert_true(
        str_contains($src, 'Kernel') || str_contains($src, 'Docs'),
        'workflow must run at least Kernel or Docs suite (fast gates)'
    );
});

test('platform-tests.yml declares mysql service and DB migrate for Integrations', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'mysql:8'), 'workflow must use mysql:8.x service container');
    assert_true(
        str_contains($src, 'scripts/migrate.php') || str_contains($src, 'scripts/install.php'),
        'workflow must apply platform schema before Integrations tests'
    );
});

test('platform-tests.yml exposes human-readable job names', function () use ($workflowPath): void {
    $src = (string) file_get_contents($workflowPath);
    assert_true(str_contains($src, 'platform-fast-gates'), 'job name platform-fast-gates required (U3)');
    assert_true(str_contains($src, 'platform-integration-gates'), 'job name platform-integration-gates required (U3)');
});
