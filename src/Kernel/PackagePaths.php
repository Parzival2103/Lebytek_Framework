<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel;

/**
 * Rutas que pertenecen al paquete Composer lebytek/framework.
 * Distinto de ROOT_PATH (raíz del proyecto consumidor).
 *
 * SQL/datos: resolveDataFile busca primero en el paquete, luego en ROOT_PATH
 * (negocio / overrides del consumidor).
 */
final class PackagePaths
{
    public static function root(): string
    {
        // este archivo: {package}/src/Kernel/PackagePaths.php
        return dirname(__DIR__, 2);
    }

    public static function schema(string $relative = 'schema.sql'): string
    {
        return self::root() . '/database/schema/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function moduleSchema(string $moduleFile): string
    {
        return self::schema('modules/' . ltrim(str_replace('\\', '/', $moduleFile), '/'));
    }

    public static function seedsDir(): string
    {
        return self::root() . '/database/seeds';
    }

    public static function resolveDataFile(string $relative): string
    {
        $rel = ltrim(str_replace('\\', '/', $relative), '/');
        $inPackage = self::root() . '/' . $rel;
        if (is_readable($inPackage)) {
            return $inPackage;
        }
        if (defined('ROOT_PATH')) {
            $inRoot = ROOT_PATH . '/' . $rel;
            if (is_readable($inRoot)) {
                return $inRoot;
            }
        }
        throw new \RuntimeException(
            "Archivo de datos no encontrado en paquete ni en ROOT_PATH: {$rel}"
        );
    }
}
