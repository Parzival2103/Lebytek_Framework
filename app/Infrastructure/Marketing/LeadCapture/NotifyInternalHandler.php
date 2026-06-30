<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;

final class NotifyInternalHandler implements LeadCaptureHandlerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $destino = ''
    ) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        if ($this->destino === '') {
            return $resultadoPrevio; // sin destino configurado: paso inerte
        }
        $html = 'Email: ' . htmlspecialchars($draft->email())
              . '<br>Tel: ' . htmlspecialchars($draft->telefono() ?? '-')
              . '<br><br>' . nl2br(htmlspecialchars($draft->mensaje() ?? ''));
        $this->mailer->enviar(new MensajeCorreo(
            $this->destino,
            'Equipo',
            'Nuevo lead: ' . $draft->nombre(),
            $html
        ));
        return $resultadoPrevio;
    }
}
