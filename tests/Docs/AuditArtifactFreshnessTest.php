<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/**
 * @return list<\DateTimeImmutable>
 */
function audit_freshness_merged_report_dates(string $auditsDir): array
{
    $dates = [];
    foreach (glob($auditsDir . '/*-auditoria-tecnica-diaria.md') ?: [] as $path) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})-auditoria-tecnica-diaria\.md$/', basename($path), $m)) {
            $dates[] = new \DateTimeImmutable($m[1] . 'T00:00:00Z');
        }
    }
    usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

    return $dates;
}

/**
 * @return list<\DateTimeImmutable>
 */
function audit_freshness_open_pr_dates(string $root): array
{
    $cmd = 'gh pr list --repo Parzival2103/Lebytek_Framework'
        . ' --search "docs(audit):" --state open'
        . ' --json title --limit 20 2>/dev/null';
    $json = shell_exec($cmd);
    if ($json === null || trim($json) === '') {
        return [];
    }
    $rows = json_decode($json, true);
    if (!is_array($rows)) {
        return [];
    }
    $dates = [];
    foreach ($rows as $row) {
        $title = (string) ($row['title'] ?? '');
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $title, $m)) {
            $dates[] = new \DateTimeImmutable($m[1] . 'T00:00:00Z');
        }
    }

    return $dates;
}

test('merged audit report is not stale while a newer open audit PR exists (M7)', function () use ($root): void {
    $auditsDir = $root . '/docs/audits';
    $mergedDates = audit_freshness_merged_report_dates($auditsDir);
    assert_true($mergedDates !== [], 'docs/audits must contain at least one *-auditoria-tecnica-diaria.md');

    $openDates = audit_freshness_open_pr_dates($root);
    if ($openDates === []) {
        fwrite(STDOUT, "  SKIP  no open docs(audit): PRs (gh unavailable or none open)\n");
        return;
    }

    $merged = $mergedDates[0];
    $newestOpen = $openDates[0];
    foreach ($openDates as $d) {
        if ($d > $newestOpen) {
            $newestOpen = $d;
        }
    }

    $deltaDays = (int) $merged->diff($newestOpen)->format('%r%a');
    assert_true(
        $deltaDays <= 2,
        sprintf(
            'Merged audit in main is stale while a newer open audit PR exists (M7). '
                . 'Latest merged report: %s; newest open audit PR date: %s; delta=%d days. '
                . 'Recovery: gh pr merge <audit-pr> --squash before close (AUTOMATION-03 Enfoque B).',
            $merged->format('Y-m-d'),
            $newestOpen->format('Y-m-d'),
            $deltaDays
        )
    );
});
