<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH.'/app');

require ROOT_PATH.'/vendor/autoload.php';

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$outDir = ROOT_PATH.'/.superpowers/sdd';
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$welcome = ViewHelper::render('emails/lead_welcome', [
    'nombre'        => '{{nombre}}',
    'landingUrl'    => '{{landing_url}}',
    'empresaNombre' => 'Lebytek',
    'codigo'        => '{{codigo}}',
    'verifyUrl'     => '{{verify_url}}',
], '');

$credentials = ViewHelper::render('emails/lead_api_credentials', [
    'nombre'          => '{{nombre}}',
    'token'           => '{{token}}',
    'apiBaseUrl'      => '{{api_base_url}}',
    'docsUrl'         => '{{docs_url}}',
    'showDocsCta'     => true,
    'showPackagesCta' => true,
    'packagesUrl'     => '{{packages_url}}',
], '');

$membership = ViewHelper::render('emails/membership_activated', [
    'nombre'     => '{{nombre}}',
    'planNombre' => '{{plan}}',
    'ciclo'      => '{{ciclo}}',
    'cuota'      => '{{cuota}}',
    'apiBaseUrl' => '{{api_base_url}}',
    'token'      => '{{token}}',
], '');

file_put_contents($outDir.'/seed-lead_welcome.html', $welcome);
file_put_contents($outDir.'/seed-lead_api_credentials.html', $credentials);
file_put_contents($outDir.'/seed-membership_activated.html', $membership);

fwrite(STDOUT, 'exported: '.strlen($welcome).' '.strlen($credentials).' '.strlen($membership)."\n");
