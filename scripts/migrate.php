<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| migrate.php — Schema SQL base del PAQUETE
|--------------------------------------------------------------------------
| Uso:
|   php scripts/migrate.php
|   php vendor/lebytek/framework/scripts/migrate.php
|
| ROOT_PATH = proyecto consumidor (.env, config)
| PackagePaths = SQL de plataforma
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

$pdo = Connection::getInstance();
$sql = file_get_contents(PackagePaths::schema('schema.sql'));

echo "=== Ejecutando migraciones de plataforma ===\n\n";

try {
    $pdo->exec($sql);
    echo "✓ Schema de plataforma aplicado correctamente.\n";
} catch (\PDOException $e) {
    echo "✗ Error al ejecutar el schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migración completada ===\n";
