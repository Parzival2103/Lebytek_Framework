<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\LeadTeamAlertNotifierInterface;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class LeadVerifiedWhatsAppNotifier implements LeadTeamAlertNotifierInterface
{
    public function __construct(
        private readonly MessageChannelInterface $whatsapp,
        private readonly bool $enabled,
    ) {}

    /** @param array<string, mixed> $lead */
    public function notifyLeadVerified(array $lead): void
    {
        if (! $this->enabled) {
            return;
        }

        $raw = (string) EnvLoader::get('MKT_ALERT_WHATSAPP_NUMBERS', '');
        $numbers = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($numbers === []) {
            return;
        }

        $base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $id = (int) ($lead['id'] ?? 0);
        $adminUrl = $base . '/crud/mkt_leads/' . $id;

        $mensaje = trim((string) ($lead['mensaje'] ?? ''));
        if (mb_strlen($mensaje) > 120) {
            $mensaje = mb_substr($mensaje, 0, 117) . '...';
        }

        $body = "Lead verificado (email OK)\n"
            . 'Nombre: ' . ($lead['nombre'] ?? '') . "\n"
            . 'Email: ' . ($lead['email'] ?? '') . "\n"
            . 'Tel: ' . ($lead['telefono'] ?? '-') . "\n"
            . ($mensaje !== '' ? "Mensaje: {$mensaje}\n" : '')
            . "Admin: {$adminUrl}";

        foreach ($numbers as $phone) {
            $result = $this->whatsapp->send(new MessageRequest('whatsapp', $phone, $body, [
                'source'    => 'lead_email_verified',
                'record_id' => $id,
            ]));

            if (! $result->ok) {
                AppLogger::error('[LeadVerifiedWA] WhatsApp alert failed', [
                    'phone' => $phone,
                    'error' => $result->error,
                    'lead_id' => $id,
                ]);
            }
        }
    }
}
