<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\Channels;

use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Integrations\MessageResult;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;

/*
|--------------------------------------------------------------------------
| EmailChannel — adapta el MailerInterface existente al puerto de canal.
|--------------------------------------------------------------------------
| Demuestra que la abstracción es realmente multi-canal desde el día 1:
| cambiar channel de "whatsapp" a "email" reusa el correo ya configurado.
*/
final class EmailChannel implements MessageChannelInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function key(): string
    {
        return 'email';
    }

    public function send(MessageRequest $request): MessageResult
    {
        try {
            $this->mailer->enviar(new MensajeCorreo(
                $request->recipient,
                (string) ($request->meta['name'] ?? ''),
                (string) ($request->meta['subject'] ?? 'Notificación'),
                $request->body
            ));
            return MessageResult::sent('email');
        } catch (\Throwable $e) {
            return MessageResult::failed($e->getMessage());
        }
    }
}
