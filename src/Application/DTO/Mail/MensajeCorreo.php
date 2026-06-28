<?php

declare(strict_types=1);

namespace App\Application\DTO\Mail;

/*
|--------------------------------------------------------------------------
| MensajeCorreo — DTO de correo saliente
|--------------------------------------------------------------------------
*/

final class MensajeCorreo
{
    public function __construct(
        public readonly string $destinatario,
        public readonly string $nombreDestinatario,
        public readonly string $asunto,
        public readonly string $html
    ) {
    }
}
