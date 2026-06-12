<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Mail\MensajeCorreo;
use App\Domain\Entities\Usuario;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\MailerInterface;
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Logging\AppLogger;

/*
|--------------------------------------------------------------------------
| CorreoAuthService — Correos de verificación y recuperación
|--------------------------------------------------------------------------
| Arma la URL absoluta con el token en claro, renderiza la plantilla
| (Views/emails/) y delega al MailerInterface. Un fallo del transporte
| se loguea y se traduce a un mensaje genérico (spec §5).
*/

final class CorreoAuthService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $baseUrl
    ) {
    }

    public function enviarVerificacion(Usuario $usuario, string $token): void
    {
        $url  = $this->url('/registro/verificar', $token);
        $html = ViewHelper::render('emails/verificacion', [
            'nombre' => $usuario->nombre(),
            'url'    => $url,
        ], '');

        $this->enviar(new MensajeCorreo(
            destinatario:       (string) $usuario->email(),
            nombreDestinatario: $usuario->nombreCompleto(),
            asunto:             'Confirma tu correo',
            html:               $html
        ));
    }

    public function enviarRecuperacion(Usuario $usuario, string $token): void
    {
        $url  = $this->url('/restablecer', $token);
        $html = ViewHelper::render('emails/recuperacion', [
            'nombre' => $usuario->nombre(),
            'url'    => $url,
        ], '');

        $this->enviar(new MensajeCorreo(
            destinatario:       (string) $usuario->email(),
            nombreDestinatario: $usuario->nombreCompleto(),
            asunto:             'Restablece tu contraseña',
            html:               $html
        ));
    }

    private function url(string $path, string $token): string
    {
        return rtrim($this->baseUrl, '/') . $path . '?token=' . rawurlencode($token);
    }

    private function enviar(MensajeCorreo $mensaje): void
    {
        try {
            $this->mailer->enviar($mensaje);
        } catch (\Throwable $e) {
            AppLogger::error('[mail] Fallo al enviar correo de auth', [
                'para'   => $mensaje->destinatario,
                'asunto' => $mensaje->asunto,
                'error'  => $e->getMessage(),
            ]);
            throw new ValidationException('No fue posible enviar el correo. Intenta más tarde.');
        }
    }
}
