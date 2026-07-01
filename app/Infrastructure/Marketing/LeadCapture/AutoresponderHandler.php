<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class AutoresponderHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $html = ViewHelper::render('emails/lead_welcome', [
            'nombre'        => $draft->nombre(),
            'landingUrl'    => rtrim((string) EnvLoader::get('APP_URL', ''), '/'),
            'empresaNombre' => null,
        ], '');

        $this->mailer->enviar(new MensajeCorreo(
            $draft->email(),
            $draft->nombre(),
            'Recibimos tu solicitud — WhatsApp API para tu negocio',
            $html,
        ));

        return $resultadoPrevio;
    }
}
