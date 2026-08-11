<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('routes/api.php registers GET /api/health before AuthMiddleware group', function () use ($root): void {
    $path = $root . '/routes/api.php';
    $src = (string) file_get_contents($path);

    assert_true(
        str_contains($src, "/api/health"),
        'missing GET /api/health in routes/api.php — register BEFORE $router->group with AuthMiddleware '
        . '(spec M4: docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md)'
    );

    $groupPos = strpos($src, '$router->group');
    $healthPos = strpos($src, '/api/health');
    assert_true(
        $groupPos !== false && $healthPos !== false && $healthPos < $groupPos,
        'GET /api/health must appear BEFORE $router->group([...AuthMiddleware...]) so LB/cron work without session'
    );
});

test('skeleton/routes/api.php mirrors harness public health route', function () use ($root): void {
    $harness = (string) file_get_contents($root . '/routes/api.php');
    $skeleton = (string) file_get_contents($root . '/skeleton/routes/api.php');

    assert_true(str_contains($skeleton, "/api/health"), 'skeleton/routes/api.php must register /api/health');
    assert_true(
        str_contains($skeleton, 'HealthController::class'),
        'skeleton must use HealthController for health endpoint'
    );

    $hGroup = strpos($harness, '$router->group');
    $sGroup = strpos($skeleton, '$router->group');
    $hHealth = strpos($harness, '/api/health');
    $sHealth = strpos($skeleton, '/api/health');
    assert_true($hHealth < $hGroup && $sHealth < $sGroup, 'both files must register /api/health before auth group');
});

test('HealthController declares public health() method', function () use ($root): void {
    $path = $root . '/src/Presentation/Controllers/Api/HealthController.php';
    $src = (string) file_get_contents($path);
    assert_true(
        preg_match('/function\s+health\s*\(/', $src) === 1,
        'HealthController must declare public health() returning JSON liveness payload'
    );
});
