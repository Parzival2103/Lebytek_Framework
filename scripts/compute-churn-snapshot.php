<?php

declare(strict_types=1);

/**
 * Computes monthly churn + demo conversion snapshot into rep_churn_monthly.
 *
 * Cron (VPS): 15 3 1 * * php /path/to/scripts/compute-churn-snapshot.php
 * Default: previous calendar month. Optional args: YYYY MM
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Application\Marketing\ComputeChurnSnapshotService;
use App\Infrastructure\Marketing\PdoChurnMetricsRepository;
use Lebytek\Framework\Kernel\Config\Config;
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
]);

if (isset($argv[1], $argv[2]) && preg_match('/^\d{4}$/', $argv[1]) && preg_match('/^(0?[1-9]|1[0-2])$/', $argv[2])) {
    $year = (int) $argv[1];
    $month = (int) $argv[2];
} else {
    $prev = new DateTimeImmutable('first day of last month');
    $year = (int) $prev->format('Y');
    $month = (int) $prev->format('n');
}

$repo = new PdoChurnMetricsRepository();
$svc = new ComputeChurnSnapshotService($repo);
$snapshot = $svc->computeAndSave($year, $month);

fwrite(STDOUT, json_encode($snapshot, JSON_THROW_ON_ERROR)."\n");
