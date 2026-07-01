<?php

declare(strict_types=1);

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

$days = (int) ($argv[1] ?? 30);
if ($days < 1) {
    fwrite(STDERR, "Uso: php scripts/expire-api-demos.php [dias=30]\n");
    exit(1);
}

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
    maxRetries: (int) EnvLoader::get('LEBYTEK_API_RETRY_MAX', 3),
);
$service = new LeadApiDeprovisioningService($api, new PdoLeadRepository());
$result = $service->expireDemosOlderThanDays($days);

fwrite(STDOUT, "expire_days={$days}\n");
fwrite(STDOUT, 'processed='.$result['processed']."\n");
fwrite(STDOUT, 'failed='.$result['failed']."\n");
foreach ($result['errors'] as $error) {
    fwrite(STDOUT, 'error='.$error."\n");
}

exit($result['failed'] > 0 ? 1 : 0);
