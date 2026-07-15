#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Lebytek\Framework\Kernel\EnvLoader;
use PHPMailer\PHPMailer\PHPMailer;

EnvLoader::load(dirname(__DIR__).'/.env');

$to = $argv[1] ?? 'brandonmartinez@lebytek.com';
$host = $argv[2] ?? '216.245.211.2';
$port = (int) ($argv[3] ?? 587);
$enc = $argv[4] ?? 'tls';

$user = (string) EnvLoader::get('MAIL_USERNAME', '');
$pass = (string) EnvLoader::get('MAIL_PASSWORD', '');
$from = (string) EnvLoader::get('MAIL_FROM_ADDRESS', $user);

echo "host={$host} port={$port} enc={$enc} user={$user} to={$to}\n";

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = $host;
$mail->Port = $port;
$mail->SMTPAuth = true;
$mail->Username = $user;
$mail->Password = $pass;
$mail->CharSet = 'UTF-8';
$mail->Timeout = 20;
$mail->SMTPDebug = 0;

if ($enc === 'ssl') {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
} elseif ($enc === 'tls') {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
}

$mail->setFrom($from, 'Autorizaciones Lebytek');
$mail->addAddress($to);
$mail->Subject = 'Prueba SMTP Lebytek '.date('H:i:s');
$mail->Body = '<p>Prueba desde scripts/smtp-probe.php</p>';
$mail->isHTML(true);

try {
    $mail->send();
    echo "[OK] sent\n";
    exit(0);
} catch (Throwable $e) {
    echo '[ERROR] '.$e->getMessage()."\n";
    exit(1);
}
