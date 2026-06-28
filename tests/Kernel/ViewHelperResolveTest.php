<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('ViewHelper resuelve una vista del paquete (admin/dashboard/index)', function (): void {
    $path = ViewHelper::resolve('admin/dashboard/index');
    assert_true(is_file($path), "resolve debe devolver un archivo existente, got: {$path}");
});

test('ViewHelper::resolve lanza si la vista no existe', function (): void {
    assert_throws(\RuntimeException::class, function (): void {
        ViewHelper::resolve('no/existe/jamas');
    });
});
