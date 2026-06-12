<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Interfaces\MailerInterface;
use PHPMailer\PHPMailer\PHPMailer;

/*
|--------------------------------------------------------------------------
| PhpMailerMailer — Driver SMTP real (config/mail.php)
|--------------------------------------------------------------------------
| Lanza excepción si el transporte falla; el caller (CorreoAuthService)
| la loguea y la traduce a un mensaje genérico.
*/

final class PhpMailerMailer implements MailerInterface
{
    /** @param array{host:string,port:int,username:string,password:string,from_address:string,from_name:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function enviar(MensajeCorreo $mensaje): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('phpmailer/phpmailer no está instalado (ejecuta composer install).');
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host    = (string) $this->config['host'];
        $mail->Port    = (int) $this->config['port'];
        $mail->CharSet = 'UTF-8';

        if (($this->config['username'] ?? '') !== '') {
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) $this->config['username'];
            $mail->Password   = (string) $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $this->config['from_address'], (string) $this->config['from_name']);
        $mail->addAddress($mensaje->destinatario, $mensaje->nombreDestinatario);

        $mail->isHTML(true);
        $mail->Subject = $mensaje->asunto;
        $mail->Body    = $mensaje->html;
        $mail->AltBody = trim(strip_tags($mensaje->html));

        $mail->send();
    }
}
