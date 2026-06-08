<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| seed.php — Bootstrap SQL (schema + módulos opcionales)
|--------------------------------------------------------------------------
| Uso:
|   php scripts/seed.php
|   php scripts/seed.php --crud-engine
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

use App\Infrastructure\Install\SqlFileRunner;
use App\Kernel\EnvLoader;
use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$runner = new SqlFileRunner();
$incluirCrudEngine = in_array('--crud-engine', $argv ?? [], true);

$archivos = [
    ROOT_PATH . '/database/schema/schema.sql',
];

if ($incluirCrudEngine) {
    $archivos[] = ROOT_PATH . '/database/schema/modules/crud-engine.sql';
}

echo '=== Bootstrap SQL — ' . count($archivos) . " archivo(s) ===\n\n";

foreach ($archivos as $path) {
    $name = str_replace(ROOT_PATH . '/', '', $path);
    echo "→ {$name}\n";
    $runner->ejecutar($path);
    echo "   ✓ OK\n";
}

echo "\n=== Bootstrap completado ===\n";
