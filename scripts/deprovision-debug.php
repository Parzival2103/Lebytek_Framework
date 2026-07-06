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

$tenantPublicId = $argv[1] ?? '';
if ($tenantPublicId === '') {
    $pdo = Connection::getInstance();
    $row = $pdo->query(
        "SELECT id, api_tenant_public_id, external_ref, estado FROM dom_mkt_leads
         WHERE api_tenant_public_id IS NOT NULL ORDER BY id DESC LIMIT 1"
    )->fetch(\PDO::FETCH_ASSOC);
    if (is_array($row)) {
        fwrite(STDOUT, 'latest_lead='.json_encode($row)."\n");
        $tenantPublicId = (string) ($row['api_tenant_public_id'] ?? '');
    }
}

if ($tenantPublicId === '') {
    fwrite(STDERR, "Uso: php scripts/deprovision-debug.php [tenantPublicId]\n");
    exit(1);
}

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
    maxRetries: 1,
);

try {
    $raw = (new ReflectionClass($api))->getMethod('request');
    $raw->setAccessible(true);
    $decoded = $raw->invoke($api, 'GET', '/instances?perPage=100', null, ['X-Tenant-Id: '.$tenantPublicId]);
    fwrite(STDOUT, 'raw_keys='.implode(',', array_keys($decoded))."\n");
    fwrite(STDOUT, 'raw_json='.json_encode($decoded, JSON_UNESCAPED_UNICODE)."\n");
    $listed = $api->listInstances($tenantPublicId);
    fwrite(STDOUT, 'listed_count='.count($listed)."\n");

    if (in_array('--delete', $argv, true) && $listed !== []) {
        $instancePublicId = (string) ($listed[0]['publicId'] ?? '');
        if ($instancePublicId !== '') {
            $deleteResult = $api->deleteInstance($tenantPublicId, $instancePublicId);
            fwrite(STDOUT, 'delete_result='.json_encode($deleteResult)."\n");
        }
    }
} catch (LebytekApiException $e) {
    fwrite(STDERR, 'API error: '.$e->getMessage().' (HTTP '.$e->statusCode().")\n");
    exit(1);
}
