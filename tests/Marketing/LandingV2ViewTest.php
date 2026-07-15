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

test('v2 pricing renderiza toggle, precios data-*, destacado y features (array y JSON)', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['comprasHabilitadas' => true, 'paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199.00', 'precio_anual' => '1759.00',
         'features' => ['1 instancia', 'Hasta 5000 mensajes']],
        ['nombre' => 'Business', 'slug' => 'business', 'precio_mensual' => '4499.00', 'precio_anual' => '3599.00',
         'destacado' => 1, 'badge' => 'Más popular', 'features' => '["Hasta 3 instancias"]'],
        ['nombre' => 'Enterprise', 'slug' => 'empresa', 'precio_mensual' => '', 'precio_anual' => '',
         'features' => ['A medida']],
    ]], '');
    assert_true(str_contains($html, 'data-period="annual"'), 'toggle anual');
    assert_true(str_contains($html, 'data-monthly="$2,199"'), 'precio mensual formateado');
    assert_true(str_contains($html, 'data-annual="$1,759"'), 'precio anual formateado');
    assert_true(str_contains($html, 'Más popular'), 'badge destacado');
    assert_true(str_contains($html, 'Hasta 5000 mensajes'), 'feature de array');
    assert_true(str_contains($html, 'Hasta 3 instancias'), 'feature desde JSON');
    assert_true(str_contains($html, 'A medida'), 'precio vacío');
    assert_true(str_contains($html, '/comprar/starter?ciclo=monthly'), 'link compra starter');
    assert_true(str_contains($html, 'data-compra-annual="/comprar/business?ciclo=annual"'), 'link compra anual business');
    assert_true(!str_contains($html, '/comprar/empresa'), 'enterprise no comprable');
});

test('v2 pricing sin compras habilitadas no muestra botón comprar', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['comprasHabilitadas' => false, 'paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199.00', 'precio_anual' => '1759.00', 'features' => ['x']],
    ]], '');
    assert_true(!str_contains($html, '/comprar/starter'), 'sin link de compra');
    assert_true(str_contains($html, 'Solicitar demo'), 'mantiene CTA demo');
});

test('v2 pricing sin paquetes no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['paquetes' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 faq renderiza preguntas, respuestas y toggles', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_faq', ['faq' => [
        'titulo' => 'Preguntas frecuentes', 'lead' => 'Respuestas rápidas',
        'items' => [
            ['pregunta' => '¿Qué es la API?', 'respuesta' => 'Envía mensajes desde tu sistema.'],
            ['pregunta' => '¿Cuánto tarda?', 'respuesta' => 'Minutos.'],
        ],
    ]], '');
    assert_true(str_contains($html, 'Preguntas frecuentes'), 'titulo');
    assert_true(str_contains($html, 'Respuestas rápidas'), 'lead');
    assert_true(str_contains($html, '¿Qué es la API?'), 'pregunta');
    assert_true(str_contains($html, 'Envía mensajes desde tu sistema.'), 'respuesta');
    assert_true(str_contains($html, 'data-faq-toggle'), 'hook de toggle');
    assert_true(str_contains($html, 'lb-faq-panel'), 'panel');
    assert_true(str_contains($html, 'data-reveal-id="faq"'), 'hook de reveal');
});

test('v2 faq sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_faq', ['faq' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 lead form postea a /lead con CSRF y campos requeridos', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_lead_form', [], '');
    assert_true(str_contains($html, 'action="/lead"'), 'postea a /lead');
    assert_true(str_contains($html, 'method="POST"'), 'método POST');
    assert_true(str_contains($html, 'name="nombre"'), 'campo nombre');
    assert_true(str_contains($html, 'name="email"'), 'campo email');
    assert_true(str_contains($html, 'name="telefono"'), 'campo teléfono');
    assert_true(str_contains($html, 'name="mensaje"'), 'campo mensaje');
    assert_true(str_contains($html, 'data-empresa-merge'), 'campo empresa opcional para merge');
    assert_true(str_contains($html, 'csrf'), 'incluye token CSRF');
    assert_true(str_contains($html, 'id="demo"'), 'ancla demo');
});

test('v2 lead form muestra flash de éxito y error', function (): void {
    $ok  = ViewHelper::render('publico/partials/v2/_lead_form', ['flashAll' => ['success' => '¡Gracias!']], '');
    assert_true(str_contains($ok, '¡Gracias!'), 'muestra éxito');
    $err = ViewHelper::render('publico/partials/v2/_lead_form', ['flashAll' => ['error' => 'Falló algo']], '');
    assert_true(str_contains($err, 'Falló algo'), 'muestra error');
});

test('layout_v2 renderiza documento completo, fuentes y assets v2', function (): void {
    $html = ViewHelper::render('publico/landing_v2', [
        'empresaNombre' => 'ACME Demo', 'empresaLogo' => '',
        'bloques' => ['hero' => ['titulo' => 'Hero V2 Full', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']],
        'paquetes' => [],
    ], 'publico/layout_v2');
    assert_true(str_contains($html, '<!DOCTYPE html>'), 'documento completo');
    assert_true(str_contains($html, 'ACME Demo'), 'nombre de empresa en nav');
    assert_true(str_contains($html, 'Hero V2 Full'), 'inyecta contenido hero');
    assert_true(str_contains($html, '/assets/publico/landing_v2.css'), 'enlaza css v2');
    assert_true(str_contains($html, '/assets/publico/landing_v2.js'), 'enlaza js v2');
    assert_true(str_contains($html, 'family=Syne'), 'carga fuente Syne');
    assert_true(str_contains($html, 'family=Space+Grotesk'), 'carga fuente Space Grotesk');
    assert_true(!str_contains($html, 'landing.css"'), 'no enlaza el css v1');
});

test('landing_v2 integra todas las secciones desde bloques y paquetes', function (): void {
    $html = ViewHelper::render('publico/landing_v2', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'comprasHabilitadas' => true,
        'bloques' => [
            'hero'        => ['titulo' => 'Hero Integrado V2', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo'],
            'trust'       => ['items' => [['valor' => '10k+', 'etiqueta' => 'Mensajes al mes']]],
            'features'    => ['titulo' => 'Funciones', 'items' => [['titulo' => 'API lista', 'texto' => 'URL y token']]],
            'testimonios' => ['items' => [['texto' => 'Excelente', 'autor' => 'Cliente X']]],
            'faq'         => ['titulo' => 'Preguntas frecuentes', 'items' => [['pregunta' => '¿Cómo empiezo?', 'respuesta' => 'Solicita una demo.']]],
        ],
        'paquetes' => [
            ['nombre' => 'Business', 'slug' => 'business', 'precio_mensual' => '4499.00', 'precio_anual' => '3599.00', 'destacado' => 1, 'features' => ['Hasta 3 instancias']],
        ],
    ], 'publico/layout_v2');
    assert_true(str_contains($html, 'Hero Integrado V2'), 'sección hero');
    assert_true(str_contains($html, '10k+'), 'sección trust');
    assert_true(str_contains($html, 'API lista'), 'sección features');
    assert_true(str_contains($html, 'Excelente'), 'sección testimonios');
    assert_true(str_contains($html, 'id="paquetes"'), 'sección pricing');
    assert_true(str_contains($html, 'id="faq"'), 'sección faq');
    assert_true(str_contains($html, '¿Cómo empiezo?'), 'pregunta faq');
    assert_true(str_contains($html, 'action="/lead"'), 'sección formulario');
});
