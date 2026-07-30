<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('AUTOMATION-03 requires gh pr merge before closing the audit PR', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-03-audit-ux.md';
    $src = (string) file_get_contents($path);
    assert_true(
        str_contains($src, 'gh pr merge'),
        'AUTOMATION-03 must document gh pr merge before close (M7 / D18). '
            . 'Sync Cursor UI after merge — see docs/automation/README.md § Sincronización.'
    );
    assert_true(
        str_contains($src, 'mergeable'),
        'AUTOMATION-03 must abort close when mergeable is not MERGEABLE (U1 actionable error)'
    );
});

test('AUTOMATION-03 forbids closing audit PR without merge', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-03-audit-ux.md';
    $src = (string) file_get_contents($path);
    assert_true(
        !preg_match('/Cierra el PR draft de auditoría/s', $src)
        || str_contains($src, 'gh pr merge'),
        'Section 3 must not instruct close-without-merge (incident M7 / PR #48)'
    );
});

test('AUTOMATION-01 forbids closing docs(audit) pull requests', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-01-daily-spec.md';
    $src = (string) file_get_contents($path);
    assert_true(
        str_contains($src, 'docs(audit):'),
        'AUTOMATION-01 must name the audit PR title prefix'
    );
    assert_true(
        str_contains($src, 'No cierres') || str_contains($src, 'prohibid'),
        'AUTOMATION-01 must forbid closing audit PRs (U3). '
            . 'Add under Prohibiciones: never close docs(audit): PRs of any date.'
    );
});
