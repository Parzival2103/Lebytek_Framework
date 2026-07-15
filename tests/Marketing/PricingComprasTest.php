<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('pricing sin comprasHabilitadas no muestra Comprar ya', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199', 'precio_anual' => '21990', 'features' => []],
    ], 'comprasHabilitadas' => false], '');
    assert_true(! str_contains($html, 'Comprar ya'));
    assert_true(str_contains($html, 'Solicitar demo'));
});

test('pricing con compras=1 y starter muestra Comprar ya', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199', 'precio_anual' => '21990', 'features' => []],
    ], 'comprasHabilitadas' => true], '');
    assert_true(str_contains($html, 'Comprar ya'));
    assert_true(str_contains($html, '/comprar/starter?ciclo=monthly'));
    assert_true(str_contains($html, 'data-compra-annual'));
});

test('pricing empresa con compras=1 muestra Contactar no Comprar ya', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => [
        ['nombre' => 'Enterprise', 'slug' => 'empresa', 'precio_mensual' => '', 'precio_anual' => '', 'features' => []],
    ], 'comprasHabilitadas' => true], '');
    assert_true(str_contains($html, 'Contactar'));
    assert_true(! str_contains($html, 'Comprar ya'));
});

test('LandingController expone comprasHabilitadas desde query', function (): void {
    $src = file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true($src !== false);
    assert_true(str_contains($src, "filter_var(\$request->query('compras'"));
    assert_true(str_contains($src, 'comprasHabilitadas'));
});
