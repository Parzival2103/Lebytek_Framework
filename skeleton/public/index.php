<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Punto de entrada único de la aplicación
|--------------------------------------------------------------------------
| Todo el tráfico HTTP pasa por este archivo.
| No debe contener lógica de negocio ni de presentación.
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('APP_START', microtime(true));

$vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_readable($vendorAutoload)) {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
}
require $vendorAutoload;

\Lebytek\Framework\Kernel\Bootstrap::run();
