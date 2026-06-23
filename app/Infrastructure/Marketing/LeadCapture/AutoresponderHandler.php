<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Domain\Interfaces\MailerInterface;
use App\Application\DTO\Mail\MensajeCorreo;

final class AutoresponderHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $cuerpo = str_replace('{{nombre}}', htmlspecialchars($draft->nombre()), 'Hola {{nombre}}, recibimos tu solicitud y te contactaremos pronto.');
        $this->mailer->enviar(new MensajeCorreo(
            $draft->email(),
            $draft->nombre(),
            'Gracias por tu interés',
            $cuerpo
        ));
        return $resultadoPrevio;
    }
}
