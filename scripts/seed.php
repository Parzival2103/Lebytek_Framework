<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| seed.php — Ejecuta semillas SQL en orden (database/seeds/*.sql)
|--------------------------------------------------------------------------
| Uso: php scripts/seed.php
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
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $lines = preg_split('/\R/', $sql) ?: [];
    $buffer = '';
    $out = [];

    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--')) {
            continue;
        }
        $buffer .= $line . "\n";

        if (preg_match('/;\s*$/', rtrim($line))) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $out[] = $stmt;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $out[] = $tail;
    }

    return $out;
}

$pattern = ROOT_PATH . '/database/seeds/*.sql';
$files = glob($pattern) ?: [];

if ($files === []) {
    echo "No se encontraron archivos en database/seeds/\n";
    exit(1);
}

sort($files, SORT_STRING);

echo "=== Semillas SQL — " . count($files) . " archivo(s) ===\n\n";

foreach ($files as $path) {
    $name = basename($path);
    echo "→ {$name}\n";

    $sqlRaw = file_get_contents($path);
    if ($sqlRaw === false) {
        fwrite(STDERR, "No se pudo leer {$path}\n");
        exit(1);
    }

    $statements = splitSqlStatements($sqlRaw);

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    echo "   ✓ OK\n";
}

echo "\n=== Semillas completadas ===\n";
