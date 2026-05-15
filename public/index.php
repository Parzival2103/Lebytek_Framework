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

// Cargar bootstrap de la aplicación
require ROOT_PATH . '/app/Kernel/Bootstrap.php';
