#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Infrastructure\Mail\LogMailer;
use Lebytek\Framework\Infrastructure\Mail\PhpMailerMailer;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(dirname(__DIR__).'/.env');
$config = require dirname(__DIR__).'/config/mail.php';

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/test-mail.php destino@correo.com\n");
    exit(1);
}

echo 'driver='.($config['driver'] ?? '?')."\n";
echo 'host='.($config['host'] ?? '').' port='.($config['port'] ?? '')."\n";
echo 'from='.($config['from_address'] ?? '')."\n";
echo "to={$to}\n";

try {
    $mailer = ($config['driver'] ?? 'log') === 'smtp'
        ? new PhpMailerMailer($config)
        : new LogMailer();

    $mailer->enviar(new MensajeCorreo(
        $to,
        'Prueba Lebytek',
        'Prueba SMTP Lebytek — '.date('c'),
        '<p>Si recibes esto, SMTP desde lebytek.com funciona.</p>'
    ));

    echo "[OK] enviar() completó sin excepción\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] '.$e->getMessage()."\n");
    exit(1);
}
