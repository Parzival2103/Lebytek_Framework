<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| migrate.php — Ejecuta el schema SQL base
|--------------------------------------------------------------------------
| Uso: php scripts/migrate.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;

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

$pdo  = Connection::getInstance();
$sql  = file_get_contents(ROOT_PATH . '/database/schema/schema.sql');

echo "=== Ejecutando migraciones ===\n\n";

try {
    $pdo->exec($sql);
    echo "✓ Schema aplicado correctamente.\n";
} catch (\PDOException $e) {
    echo "✗ Error al ejecutar el schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migración completada ===\n";
