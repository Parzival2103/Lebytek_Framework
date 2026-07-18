<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| seed.php — Bootstrap SQL del PAQUETE
|--------------------------------------------------------------------------
| Uso:
|   php scripts/seed.php
|   php scripts/seed.php --crud-engine
|   php vendor/lebytek/framework/scripts/seed.php
*/

$packageScriptsDir = __DIR__;
$packageRoot = dirname($packageScriptsDir);

if (!defined('ROOT_PATH')) {
    $candidateConsumer = dirname($packageRoot, 3);
    if (
        is_readable($candidateConsumer . '/composer.json')
        && is_dir($packageRoot . '/src')
        && str_contains((string) file_get_contents($candidateConsumer . '/composer.json'), '"type": "project"')
    ) {
        define('ROOT_PATH', $candidateConsumer);
    } else {
        define('ROOT_PATH', $packageRoot);
    }
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    $autoload = $packageRoot . '/vendor/autoload.php';
}
require_once $autoload;

use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\PackagePaths;

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
    PackagePaths::schema('schema.sql'),
];

if ($incluirCrudEngine) {
    $archivos[] = PackagePaths::moduleSchema('crud-engine.sql');
}

echo '=== Bootstrap SQL — ' . count($archivos) . " archivo(s) ===\n\n";

foreach ($archivos as $path) {
    $name = str_replace(str_replace('\\', '/', PackagePaths::root()) . '/', '', str_replace('\\', '/', $path));
    echo "→ {$name}\n";
    $runner->ejecutar($path);
    echo "   ✓ OK\n";
}

echo "\n=== Bootstrap completado ===\n";
