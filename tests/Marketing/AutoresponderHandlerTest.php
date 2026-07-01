<?php

declare(strict_types=1);

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Infrastructure\Marketing\LeadCapture\AutoresponderHandler;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

final class AutoresponderSpyMailer implements MailerInterface
{
    public ?MensajeCorreo $last = null;

    public function enviar(MensajeCorreo $m): void
    {
        $this->last = $m;
    }
}

test('AutoresponderHandler sends branded HTML welcome email', function (): void {
    $_ENV['APP_URL'] = 'https://lebytek.com';

    $mailer = new AutoresponderSpyMailer();
    $handler = new AutoresponderHandler($mailer);
    $draft = new LeadDraft('Pedro', 'pedro@test.com');
    $result = $handler->handle($draft, new LeadResult(true, 1));

    assert_same(true, $result->ok());
    assert_true($mailer->last !== null);
    assert_same('pedro@test.com', $mailer->last->destinatario);
    assert_same('Recibimos tu solicitud — WhatsApp API para tu negocio', $mailer->last->asunto);
    assert_true(str_contains($mailer->last->html, '<!DOCTYPE html>'));
    assert_true(str_contains($mailer->last->html, 'Pedro'));
    assert_true(str_contains($mailer->last->html, '#paquetes'));
    assert_true(! str_contains($mailer->last->html, 'recibimos tu solicitud y te contactaremos pronto'));
});
