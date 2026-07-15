<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\PurchaseTeamAlertNotifierInterface;
use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;
use Lebytek\Framework\Domain\Integrations\MessageRequest;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class PurchaseWhatsAppNotifier implements PurchaseTeamAlertNotifierInterface
{
    public function __construct(
        private readonly MessageChannelInterface $whatsapp,
        private readonly bool $enabled,
    ) {}

    public function notifyTransferPending(array $order): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $dedicated = trim((string) EnvLoader::get('MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS', ''));
        $raw = $dedicated !== ''
            ? $dedicated
            : (string) EnvLoader::get('MKT_ALERT_WHATSAPP_NUMBERS', '');
        $numbers = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($numbers === []) {
            return false;
        }

        $base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $id = (int) ($order['id'] ?? 0);
        $publicId = (string) ($order['public_id'] ?? '');
        $adminUrl = $base.'/crud/mkt_ordenes/'.$id;

        $body = "Nueva orden — transferencia pendiente\n"
            . 'Orden: '.$publicId."\n"
            . 'Plan: '.($order['paquete_slug'] ?? '').' / '.($order['ciclo'] ?? '')."\n"
            . 'Nombre: '.($order['nombre'] ?? '')."\n"
            . 'Email: '.($order['email'] ?? '')."\n"
            . 'Tel: '.($order['telefono'] ?? '-')."\n"
            . 'Empresa: '.($order['empresa'] ?? '')."\n"
            . "Admin: {$adminUrl}";

        $anyOk = false;
        foreach ($numbers as $phone) {
            $result = $this->whatsapp->send(new MessageRequest('whatsapp', $phone, $body, [
                'source' => 'membership_transfer_pending',
                'record_id' => $id,
            ]));

            if ($result->ok) {
                $anyOk = true;
                continue;
            }

            AppLogger::error('[PurchaseWA] WhatsApp alert failed', [
                'phone' => $phone,
                'error' => $result->error,
                'order_id' => $id,
            ]);
        }

        return $anyOk;
    }
}
