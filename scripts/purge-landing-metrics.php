<?php

declare(strict_types=1);

/**
 * Purga sesiones y eventos de landing más antiguos que `retention_days`
 * (config `landing_experiments.php`, default 90).
 *
 * **Nunca** elimina pesos ni propuestas de variantes — solo métricas crudas
 * en `dom_mkt_landing_events` y `dom_mkt_landing_sessions`.
 *
 * Cron sugerido, semanal domingo 04:00:
 *   0 4 * * 0 cd /path/to/app && php scripts/purge-landing-metrics.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Application\Marketing\PurgeLandingMetricsUseCase;
use App\Infrastructure\Marketing\PdoLandingMetricsRepository;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(ROOT_PATH.'/.env');
Config::init(ROOT_PATH.'/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
]);

$experimentsCfg = require ROOT_PATH.'/config/marketing/landing_experiments.php';

$useCase = new PurgeLandingMetricsUseCase(
    new PdoLandingMetricsRepository(),
    $experimentsCfg,
);

$result = $useCase->ejecutar();

fwrite(STDOUT, 'purged_sessions='.$result['sessions'].' purged_events='.$result['events']."\n");

exit(0);
