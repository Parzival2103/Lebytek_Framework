<?php

declare(strict_types=1);

namespace App\Domain\Interfaces;

use App\Application\DTO\Mail\MensajeCorreo;

/*
|--------------------------------------------------------------------------
| MailerInterface — Contrato de envío de correo
|--------------------------------------------------------------------------
| Implementaciones en app/Infrastructure/Mail/ (smtp real y log de dev).
| Nota de capas: el DTO vive en Application por decisión del spec
| 2026-06-12 (§5), igual que el resto del patrón del framework.
*/

interface MailerInterface
{
    /** @throws \Throwable si el transporte falla */
    public function enviar(MensajeCorreo $mensaje): void;
}
