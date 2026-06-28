<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bootstrap de la aplicación
|--------------------------------------------------------------------------
| Secuencia de inicialización antes de manejar cualquier request:
| 1. Autoloader
| 2. Variables de entorno
| 3. Configuración
| 4. Manejo de errores
| 5. Sesión
| 6. Request → Router → Response
*/

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Config\DebugMode;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\Http\Router;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Exceptions\AppException;
use Lebytek\Framework\Kernel\Logging\AppLogger;

// ── 1. Autoloader ────────────────────────────────────────────────────────────
require_once ROOT_PATH . '/vendor/autoload.php';

// ── 2. Variables de entorno ───────────────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';
if (!file_exists($envFile)) {
    copy(ROOT_PATH . '/.env.example', $envFile);
}
EnvLoader::load($envFile);

// ── 3. Configuración ──────────────────────────────────────────────────────────
Config::init(ROOT_PATH . '/config');

// ── 4. Zona horaria y reporte de errores ──────────────────────────────────────
date_default_timezone_set(Config::get('app.timezone', 'America/Mexico_City'));

$isDebug = DebugMode::resolve((string) Config::get('app.env', 'production'), (bool) Config::get('app.debug', false));

if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
}

require_once APP_PATH . '/Presentation/bootstrap_error_renderers.php';
registerPresentationErrorRenderers();

set_exception_handler(function (\Throwable $e) use ($isDebug): void {
    AppLogger::error($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code(500);

    if ($isDebug) {
        echo '<pre style="background:#1a1a1a;color:#f8f8f8;padding:20px;margin:0;">';
        echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo '<strong>Archivo:</strong> ' . $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo '<strong>Traza:</strong>' . "\n" . htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        echo Response::renderInternalError();
    }
    exit;
});

// ── 5. Sesión ─────────────────────────────────────────────────────────────────
Session::start();
Session::ageOldInput();

// ── 6. Conexión a base de datos (lazy — se conecta cuando se necesita) ────────
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => Config::get('database.charset', 'utf8mb4'),
]);

// ── 7. Contenedor de dependencias ─────────────────────────────────────────────
$container = new \Lebytek\Framework\Kernel\Container\Container();
$containerConfig = require ROOT_PATH . '/config/container.php';
$containerConfig($container);

// ── 8. Rutas y despacho ───────────────────────────────────────────────────────
$request = Request::fromGlobals();
$router  = new Router();
$router->setContainer($container);

require ROOT_PATH . '/routes/web.php';
require ROOT_PATH . '/routes/api.php';

$router->dispatch($request);
