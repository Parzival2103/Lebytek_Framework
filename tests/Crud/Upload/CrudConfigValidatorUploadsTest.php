<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('uploadsBlockErrors: sin bloque uploads pasa', function (): void {
    assert_same([], CrudConfigValidator::uploadsBlockErrors([]));
});

test('uploadsBlockErrors: uploads disabled no exige allowlist', function (): void {
    $config = [
        'uploads' => ['enabled' => false, 'public_path' => 'uploads/cruds/x'],
        'form' => ['fields' => [['name' => 'doc', 'type' => 'file']]],
    ];
    assert_same([], CrudConfigValidator::uploadsBlockErrors($config));
});

test('uploadsBlockErrors: uploads enabled exige public_path uploads/ válido', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => '../evil'],
        'form' => ['fields' => []],
    ]);
    assert_true(count($errors) >= 1, 'debe rechazar public_path inválido');
});

test('uploadsBlockErrors: campo file sin allowed_extensions cuando uploads enabled', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => 'uploads/cruds/demo'],
        'form' => ['fields' => [
            ['name' => 'adjunto', 'label' => 'Adjunto', 'type' => 'file'],
        ]],
    ]);
    assert_true(
        (bool) preg_grep('/allowed_extensions/', $errors),
        'debe mencionar allowed_extensions: ' . json_encode($errors)
    );
});

test('uploadsBlockErrors: allowed_extensions con php en denylist falla', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => 'uploads/cruds/demo'],
        'form' => ['fields' => [
            ['name' => 'adjunto', 'type' => 'file', 'validation' => ['allowed_extensions' => ['php']]],
        ]],
    ]);
    assert_true(count($errors) >= 1, 'php debe estar prohibido');
});
