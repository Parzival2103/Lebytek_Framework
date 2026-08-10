<?php

declare(strict_types=1);

test('modulo-crud-engine documenta allowlist obligatoria para uploads', function (): void {
    $doc = file_get_contents(dirname(__DIR__, 2) . '/docs/modules/crud/modulo-crud-engine.md');
    assert_true(str_contains($doc, 'allowed_extensions'), 'doc debe mencionar allowed_extensions');
    assert_true(str_contains($doc, 'uploads.enabled'), 'doc debe mencionar uploads.enabled');
    assert_true(
        str_contains($doc, 'uploads/') && str_contains($doc, 'public_path'),
        'doc debe documentar public_path bajo uploads/'
    );
});
