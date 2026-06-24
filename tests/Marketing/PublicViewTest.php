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

test('landing lista los features de un paquete (array y JSON string)', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '', 'bloques' => [],
        'paquetes' => [
            ['nombre' => 'Plan A', 'precio_mensual' => '299', 'features' => ['Soporte 24/7', 'Hasta 3 usuarios']],
            ['nombre' => 'Plan B', 'precio_mensual' => '499', 'features' => '["API incluida"]'],
        ],
    ], 'publico/layout');
    assert_true(str_contains($html, 'Soporte 24/7'), 'feature de array');
    assert_true(str_contains($html, 'Hasta 3 usuarios'), 'segundo feature de array');
    assert_true(str_contains($html, 'API incluida'), 'feature desde JSON string');
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

test('layout público inyecta las variables de tema (color primario)', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
        'primaryColor' => '#ff2200', 'primaryRgb' => '255, 34, 0',
    ], 'publico/layout');
    assert_true(str_contains($html, '--app-primary'), 'emite variable --app-primary');
    assert_true(str_contains($html, '#ff2200'), 'usa el color primario recibido');
});

test('layout público enlaza el sistema visual y las fuentes', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($html, '/assets/publico/landing.css'), 'enlaza el stylesheet público');
    assert_true(str_contains($html, '/assets/publico/landing.js'), 'enlaza el js público');
    assert_true(str_contains($html, 'fonts.googleapis.com'), 'carga Google Fonts');
});

test('footer usa columnas del bloque footer y cae a fallback sin él', function (): void {
    $conFooter = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => ['footer' => ['columnas' => [
            ['titulo' => 'Producto', 'links' => [['texto' => 'Paquetes', 'url' => '#paquetes']]],
        ], 'legal' => 'Texto legal demo']],
        'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($conFooter, 'Texto legal demo'), 'muestra legal del bloque');
    assert_true(str_contains($conFooter, 'Paquetes'), 'muestra link de columna');

    $sinFooter = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME Fallback', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($sinFooter, 'ACME Fallback'), 'footer fallback con nombre de empresa');
});

test('LandingController resuelve el tema con LebytekUiConfig', function (): void {
    $src = file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true($src !== false, 'archivo existe');
    assert_true(str_contains($src, 'LebytekUiConfig::resolve'), 'el controlador resuelve el tema');
    assert_true(str_contains($src, "'primaryColor'"), 'pasa primaryColor a la vista');
});

test('hero renderiza badge, dos CTAs y media cuando están presentes', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => [
        'titulo' => 'Titulo Hero', 'subtitulo' => 'Sub Hero', 'badge' => 'API de WhatsApp',
        'cta_texto' => 'Solicitar demo', 'cta_url' => '#demo',
        'cta2_texto' => 'Ver paquetes', 'cta2_url' => '#paquetes',
        'media' => ['img' => '/assets/publico/hero-mock.jpg', 'alt' => 'Mock demo'],
    ]], '');
    assert_true(str_contains($html, 'Titulo Hero'), 'titulo');
    assert_true(str_contains($html, 'API de WhatsApp'), 'badge');
    assert_true(str_contains($html, 'Solicitar demo'), 'cta 1');
    assert_true(str_contains($html, 'Ver paquetes'), 'cta 2');
    assert_true(str_contains($html, '/assets/publico/hero-mock.jpg'), 'media img');
    assert_true(str_contains($html, 'Mock demo'), 'media alt');
});

test('hero vacío no emite sección (degradación)', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => []], '');
    assert_true(trim($html) === '', 'sin contenido cuando no hay datos');
});

test('hero sin media no genera <img>', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => [
        'titulo' => 'Solo texto', 'subtitulo' => 'Sin media',
    ]], '');
    assert_true(str_contains($html, 'Solo texto'), 'titulo presente');
    assert_true(!str_contains($html, 'ct-hero__media'), 'sin elemento de media');
});

test('trust bar renderiza métricas cuando hay items', function (): void {
    $html = ViewHelper::render('publico/partials/_trust', ['trust' => ['items' => [
        ['valor' => 'REST API', 'etiqueta' => 'Integración simple'],
        ['valor' => '< 5 min', 'etiqueta' => 'Tiempo de setup'],
    ]]], '');
    assert_true(str_contains($html, 'REST API'), 'valor 1');
    assert_true(str_contains($html, 'Integración simple'), 'etiqueta 1');
    assert_true(str_contains($html, '&lt; 5 min'), 'valor 2 escapado');
});

test('trust bar sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_trust', ['trust' => []], '');
    assert_true(trim($html) === '', 'degradación sin items');
});

test('pricing renderiza toggle, precios formateados, destacado y features (array y JSON)', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => [
        ['nombre' => 'Básico', 'precio_mensual' => '69.00', 'precio_anual' => '599.00',
         'features' => ['5,000 mensajes/mes', '1 número de WhatsApp']],
        ['nombre' => 'Pro', 'precio_mensual' => '99.00', 'precio_anual' => '899.00',
         'destacado' => 1, 'badge' => 'Más popular', 'features' => '["30,000 mensajes/mes"]'],
        ['nombre' => 'Empresa', 'precio_mensual' => '', 'precio_anual' => '',
         'features' => ['Mensajes ilimitados']],
    ]], '');
    assert_true(str_contains($html, 'ct-billing'), 'toggle de facturación');
    assert_true(str_contains($html, 'data-period="annual"'), 'opción anual');
    assert_true(str_contains($html, 'data-annual="$599"'), 'precio anual formateado');
    assert_true(str_contains($html, 'data-monthly="$69"'), 'precio mensual formateado');
    assert_true(str_contains($html, 'ct-plan--featured'), 'plan destacado');
    assert_true(str_contains($html, 'Más popular'), 'badge');
    assert_true(str_contains($html, '5,000 mensajes/mes'), 'feature de array');
    assert_true(str_contains($html, '30,000 mensajes/mes'), 'feature desde JSON string');
    assert_true(str_contains($html, 'A medida'), 'precio vacío muestra A medida');
});

test('pricing sin paquetes no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => []], '');
    assert_true(trim($html) === '', 'degradación sin paquetes');
});

test('testimonios renderiza texto, autor y estrellas', function (): void {
    $html = ViewHelper::render('publico/partials/_testimonios', ['testimonios' => ['items' => [
        ['texto' => 'Integramos la API en una tarde', 'autor' => 'María G., E-commerce'],
    ]]], '');
    assert_true(str_contains($html, 'Integramos la API en una tarde'), 'texto');
    assert_true(str_contains($html, 'María G., E-commerce'), 'autor');
    assert_true(str_contains($html, 'bi-star-fill'), 'estrellas');
});

test('testimonios sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_testimonios', ['testimonios' => []], '');
    assert_true(trim($html) === '', 'degradación sin items');
});

test('lead form postea a /lead con CSRF y campos requeridos', function (): void {
    $html = ViewHelper::render('publico/partials/_lead_form', [], '');
    assert_true(str_contains($html, 'action="/lead"'), 'postea a /lead');
    assert_true(str_contains($html, 'method="POST"'), 'método POST');
    assert_true(str_contains($html, 'name="nombre"'), 'campo nombre');
    assert_true(str_contains($html, 'name="email"'), 'campo email');
    assert_true(str_contains($html, 'name="telefono"'), 'campo teléfono');
    assert_true(str_contains($html, 'name="mensaje"'), 'campo mensaje');
    assert_true(str_contains($html, 'csrf'), 'incluye token CSRF');
});

test('landing integra todas las secciones desde bloques y paquetes', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [
            'hero'        => ['titulo' => 'Hero Integrado', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo'],
            'trust'       => ['items' => [['valor' => 'REST API', 'etiqueta' => 'Integración simple']]],
            'testimonios' => ['items' => [['texto' => 'Excelente servicio', 'autor' => 'Cliente X']]],
        ],
        'paquetes' => [
            ['nombre' => 'Pro', 'precio_mensual' => '99.00', 'precio_anual' => '899.00', 'destacado' => 1, 'features' => ['30,000 mensajes/mes']],
        ],
    ], 'publico/layout');
    assert_true(str_contains($html, 'Hero Integrado'), 'sección hero');
    assert_true(str_contains($html, 'REST API'), 'sección trust');
    assert_true(str_contains($html, 'ct-pricing'), 'sección pricing');
    assert_true(str_contains($html, 'Excelente servicio'), 'sección testimonios');
    assert_true(str_contains($html, 'action="/lead"'), 'sección formulario');
});
