<?php

declare(strict_types=1);

// Package-root test harness: app-level paths live under skeleton/.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('SKELETON_PATH')) {
    define('SKELETON_PATH', ROOT_PATH . '/skeleton');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', SKELETON_PATH);
}
if (!defined('APP_PATH')) {
    define('APP_PATH', SKELETON_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', SKELETON_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', SKELETON_PATH . '/storage');
}

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

$envFile = SKELETON_PATH . '/.env';
if (is_readable($envFile)) {
    EnvLoader::load($envFile);
} elseif (is_readable(SKELETON_PATH . '/.env.example')) {
    EnvLoader::load(SKELETON_PATH . '/.env.example');
}

Config::init(SKELETON_PATH . '/config');
$dbConfig = Config::get('database', []);
if (is_array($dbConfig) && $dbConfig !== []) {
    Connection::configure($dbConfig);
}
