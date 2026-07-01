<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(ROOT_PATH.'/.env');

$leadId = (int) ($argv[1] ?? 0);
if ($leadId <= 0) {
    fwrite(STDERR, "Uso: php scripts/cleanup-orphan-lead-instance.php <leadId>\n");
    exit(1);
}

$externalRef = 'lebytek_lead_'.$leadId;

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
    maxRetries: 1,
);

try {
    $tenantPublicId = null;
    foreach ($api->listTenants(200) as $tenant) {
        if ((string) ($tenant['externalRef'] ?? '') === $externalRef) {
            $tenantPublicId = (string) ($tenant['publicId'] ?? '');
            break;
        }
    }

    if ($tenantPublicId === null || $tenantPublicId === '') {
        fwrite(STDERR, "No se encontró tenant con externalRef={$externalRef}\n");
        exit(1);
    }

    fwrite(STDOUT, "tenant={$tenantPublicId}\n");
    $deleted = 0;
    foreach ($api->listInstances($tenantPublicId) as $instance) {
        $instancePublicId = (string) ($instance['publicId'] ?? '');
        if ($instancePublicId === '') {
            continue;
        }
        $api->deleteInstance($tenantPublicId, $instancePublicId);
        $deleted++;
        fwrite(STDOUT, "deleted_instance={$instancePublicId}\n");
    }

    fwrite(STDOUT, "deleted_count={$deleted}\n");
} catch (LebytekApiException $e) {
    fwrite(STDERR, 'API error: '.$e->getMessage()."\n");
    exit(1);
}
