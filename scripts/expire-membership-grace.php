#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Expires past_due membresías whose 48h grace window ended.
 *
 * Cron (VPS): */30 * * * * php /path/to/scripts/expire-membership-grace.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

$envFile = ROOT_PATH.'/.env';
if (is_readable($envFile)) {
    EnvLoader::load($envFile);
} elseif (is_readable(ROOT_PATH.'/.env.example')) {
    EnvLoader::load(ROOT_PATH.'/.env.example');
}

Config::init(ROOT_PATH.'/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$container = new Container();
(require ROOT_PATH.'/config/container.php')($container);

/** @var \App\Application\Marketing\ExpireMembershipGraceService $svc */
$svc = $container->get(\App\Application\Marketing\ExpireMembershipGraceService::class);
$count = $svc->expireDue(new DateTimeImmutable('now'));
fwrite(STDOUT, "expired={$count}\n");
