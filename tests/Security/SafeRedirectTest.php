<?php

declare(strict_types=1);

use App\Kernel\Http\SafeRedirect;

// ── Caracterización: las variantes legítimas se conservan idénticas ──────────
test('SafeRedirect conserva una ruta interna simple', function (): void {
    assert_same('/admin/usuarios', SafeRedirect::toInternal('/admin/usuarios'));
});

test('SafeRedirect conserva ruta interna con query y fragmento', function (): void {
    assert_same('/admin/crud/clientes?page=2#tab', SafeRedirect::toInternal('/admin/crud/clientes?page=2#tab'));
});

test('SafeRedirect conserva la raíz', function (): void {
    assert_same('/', SafeRedirect::toInternal('/'));
});

// ── Seguridad: todo destino externo o malformado cae al fallback ─────────────
test('SafeRedirect rechaza URL absoluta con esquema', function (): void {
    assert_same('/', SafeRedirect::toInternal('https://evil.com/phish'));
});

test('SafeRedirect rechaza URL protocol-relative', function (): void {
    assert_same('/', SafeRedirect::toInternal('//evil.com'));
});

test('SafeRedirect neutraliza trucos con backslash', function (): void {
    assert_same('/', SafeRedirect::toInternal('/\\evil.com'));
    assert_same('/', SafeRedirect::toInternal('\\/evil.com'));
});

test('SafeRedirect rechaza inyección de cabecera (CRLF) y control chars', function (): void {
    assert_same('/', SafeRedirect::toInternal("/admin\r\nSet-Cookie: x=1"));
});

test('SafeRedirect usa el fallback dado ante entrada vacía o nula', function (): void {
    assert_same('/admin', SafeRedirect::toInternal(null, '/admin'));
    assert_same('/admin', SafeRedirect::toInternal('   ', '/admin'));
});

test('SafeRedirect rechaza rutas relativas sin slash inicial', function (): void {
    assert_same('/', SafeRedirect::toInternal('admin/usuarios'));
    assert_same('/', SafeRedirect::toInternal('javascript:alert(1)'));
});
