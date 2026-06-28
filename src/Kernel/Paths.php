<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel;

/**
 * Resuelve rutas del proyecto consumidor (esqueleto/app).
 * En una app instalada, ROOT_PATH apunta al esqueleto.
 * En tests del paquete monorepo, APP_ROOT apunta a skeleton/ y ROOT_PATH al repo del paquete.
 */
final class Paths
{
    public static function appRoot(): string
    {
        return defined('APP_ROOT') ? APP_ROOT : ROOT_PATH;
    }
}
