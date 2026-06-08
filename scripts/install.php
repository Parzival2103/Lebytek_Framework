<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| install.php — Instalador idempotente de la aplicación
|--------------------------------------------------------------------------
| Aplica, en orden:
|   1) database/schema/schema.sql      (estructura base)
|   2) database/migrations/*.sql        (cambios incrementales, orden por nombre)
|   3) database/seeds/*.sql             (datos base + demo)
|
| Pensado para correr en cada despliegue. Las migraciones/seeds del proyecto
| usan CREATE TABLE IF NOT EXISTS / INSERT IGNORE / guards information_schema,
| por lo que re-ejecutarlo es seguro.
|
| Uso: php scripts/install.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once APP_PATH . '/Kernel/Autoloader.php';

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

$pdo = Connection::getInstance();

/**
 * Ejecuta un archivo SQL completo (multi-statement) vía PDO::exec.
 */
function runSqlFile(\PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new \RuntimeException("No se pudo leer {$path}");
    }
    $pdo->exec($sql);
}

echo "=== Instalación de la aplicación ===\n\n";

// 1) Schema base
echo "→ Schema base\n";
runSqlFile($pdo, ROOT_PATH . '/database/schema/schema.sql');
echo "   ✓ schema.sql\n";

// 2) Migraciones (orden lexicográfico por nombre de archivo)
$migrations = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($migrations, SORT_STRING);
echo "\n→ Migraciones (" . count($migrations) . ")\n";
foreach ($migrations as $file) {
    runSqlFile($pdo, $file);
    echo '   ✓ ' . basename($file) . "\n";
}

// 3) Seeds (orden lexicográfico)
$seeds = glob(ROOT_PATH . '/database/seeds/*.sql') ?: [];
sort($seeds, SORT_STRING);
echo "\n→ Semillas (" . count($seeds) . ")\n";
foreach ($seeds as $file) {
    runSqlFile($pdo, $file);
    echo '   ✓ ' . basename($file) . "\n";
}

echo "\n=== Instalación completada ===\n";
