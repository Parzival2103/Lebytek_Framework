<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Mail;

use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use PHPMailer\PHPMailer\PHPMailer;

/*
|--------------------------------------------------------------------------
| PhpMailerMailer — Driver SMTP real (config/mail.php)
|--------------------------------------------------------------------------
| Puerto 465 → SSL implícito (SMTPS). Puerto 587 → STARTTLS.
| Lanza excepción si el transporte falla; el caller (CorreoAuthService)
| la loguea y la traduce a un mensaje genérico.
*/

final class PhpMailerMailer implements MailerInterface
{
    /**
     * @param array{
     *   host:string,
     *   port:int,
     *   username:string,
     *   password:string,
     *   from_address:string,
     *   from_name:string,
     *   encryption?:string,
     *   timeout?:int
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function enviar(MensajeCorreo $mensaje): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('phpmailer/phpmailer no está instalado (ejecuta composer install).');
        }

        $port = (int) $this->config['port'];

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = (string) $this->config['host'];
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = max(5, (int) ($this->config['timeout'] ?? 15));
        $mail->SMTPKeepAlive = false;

        if (($this->config['username'] ?? '') !== '') {
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) $this->config['username'];
            $mail->Password   = (string) $this->config['password'];
            $encryption       = $this->resolveEncryption($port);
            if ($encryption !== '') {
                $mail->SMTPSecure = $encryption;
            }
        }

        $mail->setFrom((string) $this->config['from_address'], (string) $this->config['from_name']);
        $mail->addAddress($mensaje->destinatario, $mensaje->nombreDestinatario);

        $mail->isHTML(true);
        $mail->Subject = $mensaje->asunto;
        $mail->Body    = $mensaje->html;
        $mail->AltBody = trim(strip_tags($mensaje->html));

        $mail->send();
    }

    private function resolveEncryption(int $port): string
    {
        $explicit = strtolower(trim((string) ($this->config['encryption'] ?? '')));

        return match ($explicit) {
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            'none' => '',
            '' => $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS,
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
    }
}
