<?php

declare(strict_types=1);

// Monorepo test harness: app-level paths live at repo root.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('SKELETON_PATH')) {
    define('SKELETON_PATH', ROOT_PATH);
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', ROOT_PATH);
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

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

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
