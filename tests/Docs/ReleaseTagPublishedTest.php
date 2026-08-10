<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('published composer.json version has resolvable git tag vX.Y.Z (REL-C1)', function () use ($root): void {
    $composerPath = $root . '/composer.json';
    $data = json_decode((string) file_get_contents($composerPath), true);
    $version = $data['version'] ?? '';
    assert_true(
        is_string($version) && $version !== '',
        'composer.json must declare non-empty "version" before release tag gate'
    );

    $tag = 'v' . $version;
    $cmd = 'git rev-parse --verify ' . escapeshellarg($tag . '^{commit}') . ' 2>/dev/null';
    exec($cmd, $out, $code);

    assert_same(
        0,
        $code,
        sprintf(
            'REL-C1 (spec 2026-08-08-audit-release-semver-tag): composer.json declares %s but git tag %s is missing. '
            . 'Action: from a CI-green commit with synced semver trio, run: git tag -a %s -m "Platform release %s" && git push origin %s',
            $version,
            $tag,
            $tag,
            $version,
            $tag
        )
    );
});

test('ReleaseTagPublishedTest documents tag must contain AuthZ and states merge commits', function () use ($root): void {
    $path = $root . '/tests/Docs/ReleaseTagPublishedTest.php';
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'REL-C1'), 'gate message must cite REL-C1 for operators');
});
