<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Integrations\Channels;

use Lebytek\Framework\Domain\Integrations\ApiConnectorInterface;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Domain\Integrations\MessageResult;

/*
|--------------------------------------------------------------------------
| GreenApiWhatsappChannel — envío de texto por Green API.
|--------------------------------------------------------------------------
| Green API encapsulado aquí: la normalización teléfono → chatId vive
| dentro del canal; el caller solo pasa un teléfono.
|   POST {base_url}/waInstance{instance}/sendMessage/{token}
|   body: { "chatId": "<digitos>@c.us", "message": "<texto>" }
*/
final class GreenApiWhatsappChannel implements MessageChannelInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly ApiConnectorInterface $http,
        private readonly array $config
    ) {
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $chatId = $this->toChatId($request->recipient);
        if ($chatId === null) {
            return MessageResult::failed('Teléfono inválido (sin dígitos)');
        }

        $url = sprintf(
            '%s/waInstance%s/sendMessage/%s',
            rtrim((string) ($this->config['base_url'] ?? ''), '/'),
            (string) ($this->config['instance_id'] ?? ''),
            (string) ($this->config['token'] ?? '')
        );

        $response = $this->http->request('POST', $url, [
            'chatId'  => $chatId,
            'message' => $request->body,
        ]);

        $status = (int) ($response['status'] ?? 0);
        $json = (array) ($response['json'] ?? []);

        if ($status < 200 || $status >= 300) {
            return MessageResult::failed("Green API HTTP {$status}", $response);
        }

        $idMessage = (string) ($json['idMessage'] ?? '');
        if ($idMessage === '') {
            return MessageResult::failed('Respuesta Green API sin idMessage', $response);
        }

        return MessageResult::sent($idMessage, $response);
    }

    /** Convierte un teléfono libre a "<solo-digitos>@c.us"; null si no hay dígitos. */
    private function toChatId(string $recipient): ?string
    {
        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($digits === '') {
            return null;
        }
        return $digits . '@c.us';
    }
}
