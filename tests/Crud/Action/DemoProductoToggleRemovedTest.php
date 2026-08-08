<?php

declare(strict_types=1);

test('DemoProductoToggleStatusHandler class is removed (G15)', function (): void {
    $path = dirname(__DIR__, 3) . '/src/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php';
    assert_true(!is_file($path), 'archivo handler toggle debe estar borrado');
    assert_true(
        !class_exists(\Lebytek\Framework\Application\Crud\Handlers\DemoProductoToggleStatusHandler::class, true),
        'autoload no debe resolver DemoProductoToggleStatusHandler'
    );
});

test('crud_handlers registry no registra demo_producto_toggle (G15)', function (): void {
    foreach (['config/crud_handlers.php', 'skeleton/config/crud_handlers.php'] as $rel) {
        $map = require dirname(__DIR__, 3) . '/' . $rel;
        assert_true(is_array($map), "{$rel} debe devolver array");
        assert_true(!array_key_exists('demo_producto_toggle', $map), "{$rel} aún mapea demo_producto_toggle");
    }
});

test('demo_productos JSON no declara action toggle (G15)', function (): void {
    foreach (['config/cruds/demo_productos.json', 'skeleton/config/cruds/demo_productos.json'] as $rel) {
        $cfg = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/' . $rel), true);
        assert_true(is_array($cfg));
        $rowNames = array_map(
            static fn ($a) => is_array($a) ? (string) ($a['name'] ?? '') : '',
            $cfg['actions']['row'] ?? []
        );
        $bulkNames = array_map(
            static fn ($a) => is_array($a) ? (string) ($a['name'] ?? '') : '',
            $cfg['actions']['bulk'] ?? []
        );
        assert_true(!in_array('toggle', $rowNames, true), "{$rel} row aún tiene toggle");
        assert_true(!in_array('toggle', $bulkNames, true), "{$rel} bulk aún tiene toggle");
        assert_true(in_array('desactivar', $rowNames, true), 'debe conservar transition desactivar');
        assert_true(in_array('activar', $rowNames, true), 'debe conservar transition activar');
    }
});
