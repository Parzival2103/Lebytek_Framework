<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Install\ModuleRegistry;
use Lebytek\Framework\Application\Install\ModuleManifest;

test('ModuleRegistry::all carga manifiestos por clave', function (): void {
    $reg = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_ok');
    $all = $reg->all();
    assert_same(2, count($all));
    assert_true(isset($all['core']) && $all['core'] instanceof ModuleManifest);
    assert_same('2.0.0', $all['crud-engine']->version);
});

test('ModuleRegistry::get devuelve null para clave inexistente', function (): void {
    $reg = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_ok');
    assert_null($reg->get('fantasma'));
});
