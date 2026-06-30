<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(dirname(__DIR__).'/.env');

$client = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
    maxRetries: 1,
);

try {
    $client->health();
    fwrite(STDOUT, "[OK] api.lebytek.com health\n");
    exit(0);
} catch (LebytekApiException $e) {
    fwrite(STDERR, '[FAIL] api health: '.$e->getMessage().' (HTTP '.$e->statusCode().")\n");
    exit(1);
}
