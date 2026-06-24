<?php

declare(strict_types=1);

namespace App\Application\Integrations;

use App\Domain\Integrations\IntegrationLogRepositoryInterface;
use App\Domain\Integrations\MessageRequest;
use App\Domain\Integrations\MessageResult;
use App\Domain\Integrations\MessageSenderInterface;

/*
|--------------------------------------------------------------------------
| NotificationDispatcher — fachada pública de envío.
|--------------------------------------------------------------------------
| Único punto que un módulo de negocio usa para enviar. Resuelve el canal,
| aplica rate-limit, delega el envío y SIEMPRE registra el intento con el
| destinatario enmascarado. NUNCA propaga excepciones: degrada a failed.
*/
final class NotificationDispatcher implements MessageSenderInterface
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly IntegrationLogRepositoryInterface $logs,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function send(MessageRequest $request): MessageResult
    {
        $channelKey = $request->channel;
        $masked = self::maskRecipient($request->recipient);

        try {
            if (!$this->channels->has($channelKey)) {
                $result = MessageResult::failed("Canal no disponible: {$channelKey}");
                $this->logs->record($channelKey, 'unknown', $masked, 'skipped', null, $result->error, $request->meta);
                return $result;
            }

            $driver = $this->channels->driver($channelKey);

            if (!$this->rateLimiter->allow($channelKey)) {
                $result = MessageResult::failed('rate_limited');
                $this->logs->record($channelKey, $driver, $masked, 'skipped', null, $result->error, $request->meta);
                return $result;
            }

            $result = $this->channels->get($channelKey)->send($request);
            $status = $result->ok ? 'sent' : 'failed';
            $this->logs->record($channelKey, $driver, $masked, $status, $result->providerMessageId, $result->error, $request->meta);
            return $result;
        } catch (\Throwable $e) {
            $result = MessageResult::failed(self::sanitizeError($e->getMessage()));
            $driver = $this->channels->has($channelKey) ? $this->channels->driver($channelKey) : 'unknown';
            $this->logs->record($channelKey, $driver, $masked, 'failed', null, $result->error, $request->meta);
            return $result;
        }
    }

    /** Enmascara teléfono/email conservando los extremos; nunca persiste el valor en claro. */
    private static function maskRecipient(string $recipient): string
    {
        $value = trim($recipient);
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', max($len, 1));
        }
        return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
    }

    /** Recorta el mensaje de error y evita volcar payloads/secretos largos. */
    private static function sanitizeError(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        return substr($clean, 0, 480);
    }
}
