<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\UploadValidator;
use Lebytek\Framework\Domain\Exceptions\ValidationException;

function up_file(string $name, int $size, int $error = UPLOAD_ERR_OK): array
{
    return ['name' => $name, 'size' => $size, 'error' => $error, 'tmp_name' => '/tmp/x'];
}

// ── Caracterización: extensión permitida válida pasa y devuelve la extensión ──
test('UploadValidator acepta extensión permitida y devuelve la extensión', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    $ext = $v->assertValid(up_file('foto.PNG', 1024), 'Foto', ['png', 'jpg'], 'image/png');
    assert_same('png', $ext);
});

test('UploadValidator rechaza allowlist null (C6 fail-closed)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('doc.pdf', 2048), 'Doc', null, 'application/pdf');
    });
});

test('UploadValidator rechaza allowlist vacía (C6 fail-closed)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('doc.pdf', 2048), 'Doc', [], 'application/pdf');
    });
});

test('UploadValidator rechaza extensión en denylist global aunque esté en allowlist', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('shell.php', 128), 'Adjunto', ['php', 'pdf'], null);
    });
});

test('UploadValidator mensaje accionable lista extensiones permitidas (U2)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    try {
        $v->assertValid(up_file('malware.exe', 1024), 'Adjunto', ['pdf', 'jpg'], null);
        assert_true(false, 'debió lanzar');
    } catch (ValidationException $e) {
        assert_same('Extensión de archivo no permitida para Adjunto. Usa: pdf, jpg.', $e->getMessage());
    }
});

test('UploadValidator omite verificación MIME cuando no se provee MIME', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    $ext = $v->assertValid(up_file('foto.png', 1024), 'Foto', ['png'], null);
    assert_same('png', $ext);
});

// ── Seguridad / robustez ─────────────────────────────────────────────────────
test('UploadValidator conserva el mensaje de extensión no permitida', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('malware.exe', 1024), 'Foto', ['png', 'jpg'], null);
    });
});

test('UploadValidator rechaza archivo que supera el tamaño máximo', function (): void {
    $v = new UploadValidator(1024); // 1 KB
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 2048), 'Foto', ['png'], 'image/png');
    });
});

test('UploadValidator rechaza MIME incoherente con la extensión (ejecutable disfrazado)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 1024), 'Foto', ['png'], 'text/x-php');
    });
});

test('UploadValidator propaga error de subida del PHP', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('foto.png', 0, UPLOAD_ERR_PARTIAL), 'Foto', ['png'], null);
    });
});
