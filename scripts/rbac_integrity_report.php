<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Informe de integridad RBAC (solo lectura)
|--------------------------------------------------------------------------
| Uso: php scripts/rbac_integrity_report.php
*/

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Application\Services\RbacIntegrityReportService;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => Config::get('database.charset', 'utf8mb4'),
]);

$container = new Container();
$containerConfig = require ROOT_PATH . '/config/container.php';
$containerConfig($container);

/** @var RbacIntegrityReportService $svc */
$svc = $container->get(RbacIntegrityReportService::class);
$report = $svc->generarInforme();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
