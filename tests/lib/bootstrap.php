<?php

declare(strict_types=1);

// tests/lib/bootstrap.php -> repo root is two levels up.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

require_once APP_PATH . '/Kernel/Autoloader.php';

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
}

use App\Kernel\Config\Config;
use App\Kernel\Database\Connection;
use App\Kernel\EnvLoader;

$envFile = ROOT_PATH . '/.env';
if (is_readable($envFile)) {
    EnvLoader::load($envFile);
} elseif (is_readable(ROOT_PATH . '/.env.example')) {
    EnvLoader::load(ROOT_PATH . '/.env.example');
}

Config::init(ROOT_PATH . '/config');
$dbConfig = Config::get('database', []);
if (is_array($dbConfig) && $dbConfig !== []) {
    Connection::configure($dbConfig);
}
