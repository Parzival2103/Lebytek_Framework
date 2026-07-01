<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
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

$pdo = Connection::getInstance();
$stmt = $pdo->query(
    "SELECT id, api_tenant_public_id, api_instance_public_id
     FROM dom_mkt_leads
     WHERE deleted = 0
       AND estado = 'demo_enviada'
       AND api_tenant_public_id IS NOT NULL
       AND (api_instance_public_id IS NULL OR api_instance_public_id = '')"
);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$updated = 0;

foreach ($rows as $row) {
    $leadId = (int) ($row['id'] ?? 0);
    $tenantPublicId = (string) ($row['api_tenant_public_id'] ?? '');
    if ($leadId <= 0 || $tenantPublicId === '') {
        continue;
    }

    $instances = $api->listInstances($tenantPublicId);
    $match = null;
    $suffix = 'lebytek_lead_'.$leadId.'_instance';
    foreach ($instances as $instance) {
        if (($instance['publicId'] ?? '') === '') {
            continue;
        }
        $match = (string) $instance['publicId'];
        break;
    }

    if ($match === null) {
        fwrite(STDOUT, "lead={$leadId} no_instance_found tenant={$tenantPublicId}\n");
        continue;
    }

    $upd = $pdo->prepare('UPDATE dom_mkt_leads SET api_instance_public_id = :iid, updated_at = NOW() WHERE id = :id');
    $upd->execute(['iid' => $match, 'id' => $leadId]);
    $updated++;
    fwrite(STDOUT, "lead={$leadId} instance={$match}\n");
}

fwrite(STDOUT, "updated={$updated}\n");
