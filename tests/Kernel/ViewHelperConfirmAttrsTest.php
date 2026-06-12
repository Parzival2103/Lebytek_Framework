<?php

declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

test('confirmAttrs genera todos los data-attributes soportados', function (): void {
    $attrs = ViewHelper::confirmAttrs([
        'body'          => '¿Eliminar registro?',
        'title'         => 'Confirmar eliminación',
        'ok'            => 'Eliminar',
        'cancel'        => 'Volver',
        'variant'       => 'danger',
        'cancelVariant' => 'secondary',
        'icon'          => 'danger',
        'emphasis'      => 'Eliminar registro',
    ]);

    assert_true(str_contains($attrs, 'data-confirm="¿Eliminar registro?"'));
    assert_true(str_contains($attrs, 'data-confirm-title="Confirmar eliminación"'));
    assert_true(str_contains($attrs, 'data-confirm-ok="Eliminar"'));
    assert_true(str_contains($attrs, 'data-confirm-cancel="Volver"'));
    assert_true(str_contains($attrs, 'data-confirm-variant="danger"'));
    assert_true(str_contains($attrs, 'data-confirm-cancel-variant="secondary"'));
    assert_true(str_contains($attrs, 'data-confirm-icon="danger"'));
    assert_true(str_contains($attrs, 'data-confirm-emphasis="Eliminar registro"'));
});

test('confirmAttrs omite claves vacías y escapa HTML', function (): void {
    $attrs = ViewHelper::confirmAttrs([
        'body'  => '¿Borrar "<b>x</b>"?',
        'title' => '',
    ]);

    assert_true(!str_contains($attrs, 'data-confirm-title'));
    assert_true(!str_contains($attrs, '<b>'));
    assert_true(str_contains($attrs, '&lt;b&gt;'));
});

test('confirmAttrs con body vacío devuelve cadena vacía', function (): void {
    assert_same('', ViewHelper::confirmAttrs([]));
});
