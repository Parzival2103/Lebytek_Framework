<?php

declare(strict_types=1);

namespace App\Kernel\Config;

use App\Kernel\EnvLoader;

/*
|--------------------------------------------------------------------------
| Config — Acceso centralizado a la configuración de la aplicación
|--------------------------------------------------------------------------
| Lee los archivos del directorio /config y permite acceso por clave
| con notación de punto (ej: "database.host", "app.name").
*/

final class Config
{
    private static array $data = [];
    private static bool  $initialized = false;

    public static function init(string $configPath): void
    {
        if (self::$initialized) {
            return;
        }

        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            self::$data[$key] = require $file;
        }

        self::$initialized = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts   = explode('.', $key);
        $current = &self::$data;

        foreach ($parts as $index => $part) {
            if ($index === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    public static function all(): array
    {
        return self::$data;
    }
}
