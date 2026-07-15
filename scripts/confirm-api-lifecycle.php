<?php

declare(strict_types=1);

/**
 * Confirma bajas async cuando la API ya no lista instancias del tenant.
 * Invocado por expire-api-demos.php (cron diario); no requiere crontab propio.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Application\Marketing\LeadApiDeprovisioningService;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Marketing\PdoLeadRepository;
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

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
    maxRetries: (int) EnvLoader::get('LEBYTEK_API_RETRY_MAX', 3),
);
$service = new LeadApiDeprovisioningService($api, new PdoLeadRepository());
$result = $service->confirmPendingDeprovisions();

fwrite(STDOUT, 'pending='.$result['pending']."\n");
fwrite(STDOUT, 'confirmed='.$result['confirmed']."\n");
foreach ($result['errors'] as $error) {
    fwrite(STDOUT, 'error='.$error."\n");
}

exit(count($result['errors']) > 0 ? 1 : 0);
