<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('vista transferencia muestra banco y referencia ORD- sin token API', function (): void {
    $html = ViewHelper::render('publico/compra_transferencia', [
        'order' => [
            'public_id' => '01JORDVIEW000000000000001',
            'paquete_slug' => 'starter',
            'ciclo' => 'monthly',
            'precio_snapshot' => 2199,
        ],
        'bank' => [
            'bank_name' => 'Banco Demo',
            'beneficiary' => 'Lebytek SA',
            'clabe' => '012345678901234567',
            'account' => '',
            'proof_guide' => 'Envía el comprobante por WhatsApp ops.',
            'reference' => 'ORD-01JORDVIEW000000000000001',
        ],
        'pageTitle' => 'Transferencia',
        'empresaNombre' => 'Lebytek',
        'empresaLogo' => '',
        'primaryColor' => '#0d6efd',
        'primaryHover' => '#0b5ed7',
        'primaryActive' => '#0a58ca',
        'primarySubtle' => '#cfe2ff',
        'primaryRgb' => '13, 110, 253',
        'lebytekCssVariables' => '',
        'bodyBg' => '#fff',
        'darkMode' => false,
    ], 'publico/layout');

    assert_true(str_contains($html, 'Instrucciones de transferencia'));
    assert_true(str_contains($html, 'ORD-01JORDVIEW000000000000001'));
    assert_true(str_contains($html, 'Banco Demo'));
    assert_true(str_contains($html, '012345678901234567'));
    assert_true(str_contains($html, 'Envía el comprobante por WhatsApp ops.'));
    assert_true(! str_contains($html, 'Bearer'));
    assert_true(! str_contains($html, '|membership'));
    assert_true(! preg_match('/\b\d+\|[A-Za-z0-9]+/', $html));
});
