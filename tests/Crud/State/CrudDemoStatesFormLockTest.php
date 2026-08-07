<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

/**
 * @return array{0: string, 1: array<string, mixed>}
 */
function crud_p02_load_demo(string $relative): array
{
    $path = dirname(__DIR__, 3) . '/' . $relative;
    assert_true(is_file($path), "falta {$relative}");
    $data = json_decode((string) file_get_contents($path), true);
    assert_true(is_array($data), "JSON inválido: {$relative}");

    return [$path, $data];
}

function crud_p02_form_names(array $config): array
{
    $names = [];
    foreach (($config['form']['fields'] ?? []) as $field) {
        if (is_array($field) && isset($field['name'])) {
            $names[] = (string) $field['name'];
        }
    }

    return $names;
}

test('demo_productos: form no incluye states.column status (C3)', function (): void {
    foreach (['config/cruds/demo_productos.json', 'skeleton/config/cruds/demo_productos.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('status', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('status', crud_p02_form_names($cfg), true), "{$rel} aún tiene status en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg), "{$rel} statesBlockErrors debe ser []");
    }
});

test('demo_pedidos: form no incluye states.column status (C3)', function (): void {
    foreach (['config/cruds/demo_pedidos.json', 'skeleton/config/cruds/demo_pedidos.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('status', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('status', crud_p02_form_names($cfg), true), "{$rel} aún tiene status en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg));
    }
});

test('demo_citas: form no incluye states.column estado (C3)', function (): void {
    foreach (['config/cruds/demo_citas.json', 'skeleton/config/cruds/demo_citas.json'] as $rel) {
        [, $cfg] = crud_p02_load_demo($rel);
        assert_same('estado', (string) ($cfg['states']['column'] ?? ''));
        assert_true(!in_array('estado', crud_p02_form_names($cfg), true), "{$rel} aún tiene estado en form");
        assert_same([], CrudConfigValidator::statesBlockErrors($cfg));
    }
});
