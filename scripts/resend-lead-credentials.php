#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Re-emite token por tenant del lead y reenvía correo de credenciales.
 * Uso: php scripts/resend-lead-credentials.php <lead_id>
 */

require dirname(__DIR__).'/vendor/autoload.php';

use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Infrastructure\Mail\PhpMailerMailer;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

define('ROOT_PATH', dirname(__DIR__));
EnvLoader::load(ROOT_PATH.'/.env');
Config::init(ROOT_PATH.'/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$leadId = (int) ($argv[1] ?? 0);
if ($leadId <= 0) {
    fwrite(STDERR, "Uso: php scripts/resend-lead-credentials.php <lead_id>\n");
    exit(1);
}

$pdo = Connection::getInstance();
$stmt = $pdo->prepare('SELECT id, nombre, email, api_tenant_public_id FROM dom_mkt_leads WHERE id = ? AND deleted = 0 LIMIT 1');
$stmt->execute([$leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lead === false) {
    fwrite(STDERR, "Lead #{$leadId} no encontrado.\n");
    exit(1);
}

$tenantPublicId = (string) ($lead['api_tenant_public_id'] ?? '');
$email = (string) ($lead['email'] ?? '');
$nombre = (string) ($lead['nombre'] ?? 'Cliente');

if ($tenantPublicId === '' || $email === '') {
    fwrite(STDERR, "Lead sin tenant provisionado o sin correo.\n");
    exit(1);
}

$api = new LebytekApiClient(
    baseUrl: (string) EnvLoader::get('LEBYTEK_API_URL', ''),
    token: (string) EnvLoader::get('LEBYTEK_API_TOKEN', ''),
    timeoutSeconds: (int) EnvLoader::get('LEBYTEK_API_TIMEOUT', 30),
);

try {
    $slug = 'lead-'.$leadId;
    $tokenResponse = $api->issueTenantToken($tenantPublicId, 'cliente-'.$slug, ['instancias.ver', 'mensajes.enviar', 'mensajes.ver']);
    $plainToken = (string) ($tokenResponse['token'] ?? '');
    if ($plainToken === '') {
        throw new LebytekApiException('API no devolvió token.');
    }
} catch (LebytekApiException $e) {
    fwrite(STDERR, '[API] '.$e->getMessage()."\n");
    exit(1);
}

$mailConfig = require dirname(__DIR__).'/config/mail.php';
$apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
$cuerpo = str_replace(
    ['{{nombre}}', '{{token}}', '{{api_base_url}}'],
    [htmlspecialchars($nombre), htmlspecialchars($plainToken), htmlspecialchars($apiBaseUrl)],
    "Hola {{nombre}},\n\nTu demo está lista. Usa este token para conectar con nuestra API:\n\nToken: {{token}}\nBase URL: {{api_base_url}}\n\nConserva este correo; el token no se vuelve a mostrar.\n\nSaludos,\nEquipo Lebytek"
);

try {
    $mailer = new PhpMailerMailer($mailConfig);
    $mailer->enviar(new MensajeCorreo(
        $email,
        $nombre,
        'Tus credenciales de acceso — Lebytek',
        nl2br($cuerpo)
    ));
    echo "[OK] Credenciales reenviadas a {$email}\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[MAIL] '.$e->getMessage()."\n");
    exit(1);
}
