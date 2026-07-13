<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('lead_welcome email renders branded HTML with CTA and no token', function (): void {
    $html = ViewHelper::render('emails/lead_welcome', [
        'nombre'        => 'María López',
        'landingUrl'    => 'https://lebytek.com',
        'empresaNombre' => 'Lebytek',
        'codigo'        => '',
        'verifyUrl'     => 'https://lebytek.com',
    ], '');

    assert_true(str_contains($html, 'María López'));
    assert_true(str_contains($html, 'Recibimos tu solicitud'));
    assert_true(str_contains($html, 'https://lebytek.com#paquetes'));
    assert_true(str_contains($html, 'Ver paquetes y precios'));
    assert_true(str_contains($html, 'API WhatsApp'));
    assert_true(str_contains($html, 'soporte@lebytek.com'));
    assert_true(! str_contains($html, 'Bearer'));
    assert_true(! str_contains($html, 'Token de acceso'));
});

test('lead_welcome email includes verification code and CTA', function (): void {
    $html = ViewHelper::render('emails/lead_welcome', [
        'nombre'        => 'María López',
        'landingUrl'    => 'https://lebytek.com',
        'empresaNombre' => 'Lebytek',
        'codigo'        => 'AB12CD',
        'verifyUrl'     => 'https://lebytek.com/verificar-demo/tokentokentokentokentokentokentokentokentokentokentoken12',
    ], '');

    assert_true(str_contains($html, 'AB12CD'));
    assert_true(str_contains($html, 'Verificar mi correo'));
    assert_true(str_contains($html, '/verificar-demo/'));
    assert_true(str_contains($html, '24 horas'));
});

test('lead_api_credentials email renders credentials and docs CTA without dashboard', function (): void {
    $html = ViewHelper::render('emails/lead_api_credentials', [
        'nombre'      => 'Carlos',
        'token'       => '12|secret-token-xyz',
        'apiBaseUrl'  => 'https://api.lebytek.com/api/v1',
        'docsUrl'     => 'https://docs.lebytek.com',
        'showDocsCta' => true,
        'showDashboardCta' => false,
    ], '');

    assert_true(str_contains($html, 'Carlos'));
    assert_true(str_contains($html, '12|secret-token-xyz'));
    assert_true(str_contains($html, 'https://api.lebytek.com/api/v1'));
    assert_true(str_contains($html, 'https://docs.lebytek.com'));
    assert_true(str_contains($html, 'Probar demo (5 min)'));
    assert_true(str_contains($html, 'Copiar al portapapeles'));
    assert_true(str_contains($html, 'símbolo'));
    assert_true(str_contains($html, 'no volverá a mostrarse'));
    assert_true(! str_contains(strtolower($html), 'dashboard'));
    assert_true(! str_contains(strtolower($html), 'waapi'));
});

test('lead_api_credentials hides docs CTA when showDocsCta is false', function (): void {
    $html = ViewHelper::render('emails/lead_api_credentials', [
        'nombre'      => 'Ana',
        'token'       => '1|tok',
        'apiBaseUrl'  => 'https://api.test/v1',
        'docsUrl'     => '',
        'showDocsCta' => false,
    ], '');

    assert_true(! str_contains($html, 'Probar demo (5 min)'));
});
