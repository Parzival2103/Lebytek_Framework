<?php
// tests/Marketing/PublicViewTest.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

test('layout público renderiza el contenido inyectado y el nombre de empresa', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME Demo',
        'empresaLogo'   => '',
        'bloques'       => ['hero' => ['titulo' => 'Hola Mundo Hero', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']],
        'paquetes'      => [],
    ], 'publico/layout');

    assert_true(str_contains($html, '<!DOCTYPE html>'), 'es documento HTML completo');
    assert_true(str_contains($html, 'ACME Demo'), 'muestra el nombre de empresa');
    assert_true(str_contains($html, 'Hola Mundo Hero'), 'renderiza el bloque hero');
    assert_true(!str_contains($html, 'AuthMiddleware'), 'sin restos de admin');
});

test('landing pública sin bloques no rompe (degradación)', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME',
        'empresaLogo'   => '',
        'bloques'       => [],
        'paquetes'      => [],
    ], 'publico/layout');
    assert_true(str_contains($html, '<!DOCTYPE html>'), 'renderiza igualmente');
});
