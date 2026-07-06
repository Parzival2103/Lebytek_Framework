<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(ROOT_PATH.'/.env');

$tenantPublicId = $argv[1] ?? '';
$instancePublicId = $argv[2] ?? '';

if ($tenantPublicId === '' || $instancePublicId === '') {
    fwrite(STDERR, "Uso: php scripts/test-delete-instance.php <tenantPublicId> <instancePublicId>\n");
    exit(1);
}

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: 30,
    maxRetries: 1,
);

try {
    $result = $api->deleteInstance($tenantPublicId, $instancePublicId);
    fwrite(STDOUT, 'OK '.json_encode($result)."\n");
} catch (LebytekApiException $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage().' HTTP='.$e->statusCode()."\n");
    exit(1);
}
