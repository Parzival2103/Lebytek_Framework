<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('routes/web.php registers CrudRbacMiddleware on CRUD routes', function () use ($root): void {
    $path = $root . '/routes/web.php';
    $src = (string) file_get_contents($path);

    assert_true(
        str_contains($src, 'CrudRbacMiddleware'),
        'missing CrudRbacMiddleware in routes/web.php — register on /crud/{resource} routes '
        . '(spec M3: docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md)'
    );

    assert_true(
        preg_match("#get\\('/crud/\\{resource\\}'[^\\n]*CrudRbacMiddleware#", $src) === 1
            || preg_match('#get\\(\'/crud/\\{resource\\}\'[^\n]*\$crudRbac#', $src) === 1,
        'GET /crud/{resource} must include CrudRbacMiddleware (or $crudRbac alias)'
    );
});

test('routes/web.php registers CrudRbacMiddleware on calendario routes', function () use ($root): void {
    $src = (string) file_get_contents($root . '/routes/web.php');

    assert_true(
        preg_match('#get\\(\'/calendario/\\{key\\}\'[^\n]*(CrudRbacMiddleware|\$crudRbac)#', $src) === 1,
        'GET /calendario/{key} must include CrudRbacMiddleware — spec M3'
    );
    assert_true(
        preg_match('#get\\(\'/calendario/\\{key\\}/eventos\'[^\n]*(CrudRbacMiddleware|\$crudRbac)#', $src) === 1,
        'GET /calendario/{key}/eventos must include CrudRbacMiddleware for AJAX 403 (U7)'
    );
});

test('skeleton/routes/web.php mirrors harness CrudRbacMiddleware registration', function () use ($root): void {
    $harness = (string) file_get_contents($root . '/routes/web.php');
    $skeleton = (string) file_get_contents($root . '/skeleton/routes/web.php');

    assert_true(str_contains($skeleton, 'CrudRbacMiddleware'), 'skeleton must use CrudRbacMiddleware');
    assert_true(
        substr_count($harness, 'CrudRbacMiddleware') === substr_count($skeleton, 'CrudRbacMiddleware'),
        'skeleton must mirror harness CrudRbacMiddleware registration count'
    );
});

test('CrudRbacMiddleware class exists in Presentation layer', function () use ($root): void {
    $path = $root . '/src/Presentation/Middlewares/CrudRbacMiddleware.php';
    assert_true(is_readable($path), 'CrudRbacMiddleware.php must exist at src/Presentation/Middlewares/');
    $src = (string) file_get_contents($path);
    assert_true(
        preg_match('/function\s+handle\s*\(/', $src) === 1,
        'CrudRbacMiddleware must declare handle(Request, callable): Response'
    );
});
