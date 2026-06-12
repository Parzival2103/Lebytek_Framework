<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Interfaces\MailerInterface;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| LogMailer — Driver de correo para desarrollo
|--------------------------------------------------------------------------
| No envía nada: escribe el correo completo a storage/logs/app-*.log
| para poder copiar la URL de verificación/recuperación en dev.
*/

final class LogMailer implements MailerInterface
{
    public function enviar(MensajeCorreo $mensaje): void
    {
        AppLogger::info('[mail:log] Correo simulado', [
            'para'   => $mensaje->destinatario,
            'nombre' => $mensaje->nombreDestinatario,
            'asunto' => $mensaje->asunto,
            'html'   => $mensaje->html,
        ]);
    }
}
