<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$kit = $root . '/docs/automation-kit';

test('automation-kit ships README, profile template and WhatsApi profile', function () use ($kit): void {
    foreach ([
        $kit . '/README.md',
        $kit . '/REPO-PROFILE.example.md',
        $kit . '/profiles/WhatsApiLebytek.md',
        $kit . '/INSTALL-WhatsApiLebytek.md',
    ] as $path) {
        assert_true(is_file($path), 'Missing kit file: ' . $path);
    }
});

test('automation-kit has nine generic AUTOMATION prompts that require REPO-PROFILE', function () use ($kit): void {
    $expected = [
        'AUTOMATION-00-daily-audit.md',
        'AUTOMATION-01-daily-spec.md',
        'AUTOMATION-02-audit-tech-debt.md',
        'AUTOMATION-03-audit-ux.md',
        'AUTOMATION-04-plan-writer.md',
        'AUTOMATION-05-wha-notify.md',
        'AUTOMATION-06-plan-readiness-gate.md',
        'AUTOMATION-07-plan-executor.md',
        'AUTOMATION-08-plan-closure.md',
    ];

    foreach ($expected as $name) {
        $path = $kit . '/' . $name;
        assert_true(is_file($path), 'Missing kit prompt: ' . $name);
        $src = (string) file_get_contents($path);
        assert_true(
            str_contains($src, '## Prompt'),
            $name . ' must expose a ## Prompt block for Cursor Automations'
        );
        assert_true(
            str_contains($src, 'REPO-PROFILE'),
            $name . ' must bind to REPO-PROFILE (portable contract)'
        );
        assert_true(
            !str_contains($src, 'Eres el auditor técnico senior del paquete Composer `lebytek/framework`'),
            $name . ' must not hardcode the Framework-only auditor identity'
        );
    }
});

test('WhatsApiLebytek profile declares composer test and api ownership', function () use ($kit): void {
    $src = (string) file_get_contents($kit . '/profiles/WhatsApiLebytek.md');
    assert_true(str_contains($src, 'Parzival2103/WhatsApiLebytek'), 'profile must name API repo');
    assert_true(str_contains($src, 'composer test'), 'profile must declare PRIMARY_TEST_CMD composer test');
    assert_true(str_contains($src, 'Lebytek_Portal'), 'profile must list Portal as sister repo');
    assert_true(str_contains($src, 'Lebytek_Framework'), 'profile must list Framework as sister repo');
});

test('Framework automation README points to portable kit', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/automation/README.md');
    assert_true(
        str_contains($src, 'docs/automation-kit/'),
        'docs/automation/README.md must link the portable kit'
    );
});
