<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Container\FrameworkServiceProvider;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;

test('FrameworkServiceProvider registra UsuarioRepositoryInterface', function (): void {
    $container = new Container();
    FrameworkServiceProvider::register($container);

    assert_true(
        $container->has(UsuarioRepositoryInterface::class),
        'FrameworkServiceProvider registra UsuarioRepositoryInterface'
    );
});
