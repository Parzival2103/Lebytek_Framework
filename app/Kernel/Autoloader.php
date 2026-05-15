<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Autoloader PSR-4 sin Composer
|--------------------------------------------------------------------------
| Registra el autoloader para el namespace App\ apuntando a /app/.
| Compatible con PHP 8.1+ y hosting compartido sin Composer disponible.
*/

spl_autoload_register(function (string $class): void {
    $prefix   = 'App\\';
    $baseDir  = APP_PATH . '/';
    $prefixLen = strlen($prefix);

    if (strncmp($prefix, $class, $prefixLen) !== 0) {
        return;
    }

    $relativeClass = substr($class, $prefixLen);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
