<?php

declare(strict_types=1);

test('compra_form incluye selector tarjeta y transferencia', function (): void {
    $html = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/compra_form.php');

    assert_true(str_contains($html, 'name="metodo_pago"'));
    assert_true(str_contains($html, 'value="stripe"'));
    assert_true(str_contains($html, 'value="transfer"'));
});

test('rutas de pago exito y cancelado existen', function (): void {
    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');

    assert_true(str_contains($routes, '/comprar/orden/{publicId}/pago/exito'));
    assert_true(str_contains($routes, '/comprar/orden/{publicId}/pago/cancelado'));
    assert_true(str_contains($routes, 'pagoExito'));
    assert_true(str_contains($routes, 'pagoCancelado'));
});

test('vista exito menciona confirmando pago', function (): void {
    $html = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/compra_pago_exito.php');

    assert_true(str_contains(mb_strtolower($html), 'confirmando'));
});
