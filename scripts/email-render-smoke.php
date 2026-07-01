<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$welcome = ViewHelper::render('emails/lead_welcome', [
    'nombre'        => 'VPS Smoke',
    'landingUrl'    => 'https://lebytek.com',
    'empresaNombre' => 'Lebytek',
], '');

$credentials = ViewHelper::render('emails/lead_api_credentials', [
    'nombre'      => 'VPS Smoke',
    'token'       => '12|smoke-test',
    'apiBaseUrl'  => 'https://api.lebytek.com/api/v1',
    'docsUrl'     => 'https://docs.lebytek.com',
    'showDocsCta' => true,
], '');

fwrite(STDOUT, 'welcome_bytes='.strlen($welcome)."\n");
fwrite(STDOUT, 'credentials_bytes='.strlen($credentials)."\n");
fwrite(STDOUT, (str_contains($welcome, '#paquetes') && str_contains($credentials, 'Ver documentación') ? 'RENDER_OK' : 'RENDER_FAIL')."\n");
