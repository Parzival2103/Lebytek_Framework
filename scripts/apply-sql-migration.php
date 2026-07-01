<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

$file = $argv[1] ?? '';
if ($file === '' || ! is_file($file)) {
    fwrite(STDERR, "Uso: php scripts/apply-sql-migration.php <ruta.sql>\n");
    exit(1);
}

EnvLoader::load(ROOT_PATH.'/.env');
Config::init(ROOT_PATH.'/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
]);

try {
    Connection::getInstance()->exec((string) file_get_contents($file));
    fwrite(STDOUT, "OK {$file}\n");
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
