<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
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

$pdo = Connection::getInstance();
$rows = $pdo->query(
    'SELECT id, nombre, email, estado, api_lifecycle_status, api_tenant_public_id,
            api_instance_public_id, api_provision_error, api_provisioned_at, updated_at
     FROM dom_mkt_leads WHERE deleted = 0 ORDER BY updated_at DESC LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);

fwrite(STDOUT, "=== LEADS (recientes) ===\n");
foreach ($rows as $row) {
    fwrite(STDOUT, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
}

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: 15,
    maxRetries: 1,
);

fwrite(STDOUT, "\n=== API INSTANCES POR TENANT ===\n");
foreach ($rows as $row) {
    $tenantId = (string) ($row['api_tenant_public_id'] ?? '');
    if ($tenantId === '') {
        continue;
    }
    $leadId = (int) ($row['id'] ?? 0);
    try {
        $instances = $api->listInstances($tenantId);
        fwrite(STDOUT, "lead={$leadId} tenant={$tenantId} instance_count=".count($instances)."\n");
        foreach ($instances as $inst) {
            fwrite(STDOUT, '  '.json_encode($inst, JSON_UNESCAPED_UNICODE)."\n");
        }
    } catch (LebytekApiException $e) {
        fwrite(STDOUT, "lead={$leadId} tenant={$tenantId} API_ERROR=".$e->getMessage()."\n");
    }
}
