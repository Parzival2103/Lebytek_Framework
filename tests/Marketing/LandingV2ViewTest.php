<?php
// tests/Marketing/LandingV2ViewTest.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('v2 hero renderiza titulo, badge, dos CTAs y visual con reveal', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_hero', ['hero' => [
        'titulo' => 'Titulo Hero V2', 'subtitulo' => 'Sub Hero', 'badge' => 'WhatsApp Business API',
        'cta_texto' => 'Solicitar demo', 'cta_url' => '#demo',
        'cta2_texto' => 'Ver paquetes', 'cta2_url' => '#paquetes',
    ]], '');
    assert_true(str_contains($html, 'Titulo Hero V2'), 'titulo');
    assert_true(str_contains($html, 'WhatsApp Business API'), 'badge');
    assert_true(str_contains($html, 'Solicitar demo'), 'cta 1');
    assert_true(str_contains($html, 'Ver paquetes'), 'cta 2');
    assert_true(str_contains($html, 'data-reveal-id="hero"'), 'hook de reveal');
});

test('v2 hero vacío no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_hero', ['hero' => []], '');
    assert_true(trim($html) === '', 'degradación sin datos');
});

test('v2 trust renderiza métricas y escapa valores', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_trust', ['trust' => ['items' => [
        ['valor' => '10k+', 'etiqueta' => 'Mensajes al mes'],
        ['valor' => '< 5 min', 'etiqueta' => 'Demo activa'],
    ]]], '');
    assert_true(str_contains($html, '10k+'), 'valor 1');
    assert_true(str_contains($html, 'Mensajes al mes'), 'etiqueta 1');
    assert_true(str_contains($html, '&lt; 5 min'), 'valor 2 escapado');
    assert_true(str_contains($html, 'data-reveal-id="trust"'), 'hook de reveal');
});

test('v2 trust sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_trust', ['trust' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 features renderiza titulo, lead e items', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_features', ['features' => [
        'titulo' => 'Integra sin complicaciones', 'lead' => 'Conecta en minutos',
        'items' => [
            ['titulo' => 'API lista', 'texto' => 'URL y token'],
            ['titulo' => 'Automatiza', 'texto' => 'Recordatorios'],
        ],
    ]], '');
    assert_true(str_contains($html, 'Integra sin complicaciones'), 'titulo');
    assert_true(str_contains($html, 'Conecta en minutos'), 'lead');
    assert_true(str_contains($html, 'API lista'), 'item 1');
    assert_true(str_contains($html, 'Recordatorios'), 'texto item 2');
    assert_true(str_contains($html, 'data-reveal-id="features"'), 'hook de reveal');
});

test('v2 features sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_features', ['features' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 testimonios renderiza texto y autor', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => ['items' => [
        ['texto' => 'Integramos en una tarde', 'autor' => 'María G. — Retail'],
    ]]], '');
    assert_true(str_contains($html, 'Integramos en una tarde'), 'texto');
    assert_true(str_contains($html, 'María G. — Retail'), 'autor');
    assert_true(str_contains($html, 'data-reveal-id="testimonials"'), 'hook de reveal');
});

test('v2 testimonios sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => []], '');
    assert_true(trim($html) === '', 'degradación');
});
