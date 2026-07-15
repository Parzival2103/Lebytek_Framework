<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Application\DTO\Mail\MensajeCorreo;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

final class AutorizarOrdenMembresiaUseCase
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly LebytekApiClient $api,
        private readonly MailerInterface $mailer,
    ) {}

    /**
     * @return array<string, mixed> orden actualizada
     */
    public function ejecutar(int $orderId, int $authorizedBy): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException('Orden no encontrada.');
        }

        $status = (string) ($order['status'] ?? '');
        if (! in_array($status, ['pending_transfer', 'awaiting_review'], true)) {
            throw new \InvalidArgumentException('La orden no está pendiente de autorización.');
        }

        $tenantPublicId = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenantPublicId === '') {
            throw new \InvalidArgumentException('Asocia el tenant demo en la orden antes de autorizar.');
        }

        $slug = (string) ($order['paquete_slug'] ?? '');
        if (! in_array($slug, ['starter', 'business', 'empresa'], true)) {
            throw new \InvalidArgumentException(
                'El paquete de la orden no es autorizable vía activate-plan (use starter, business o empresa).'
            );
        }

        $payload = [
            'planSlug' => $slug,
            'billingCycle' => (string) ($order['ciclo'] ?? 'monthly'),
            'orderExternalRef' => (string) ($order['public_id'] ?? ''),
            'tokenName' => 'membresia-'.($slug !== '' ? $slug : 'plan'),
        ];
        // Only empresa may override limits; standard slugs use api catalog.
        if ($slug === 'empresa' && isset($order['mensajes_mes_limite_snapshot']) && $order['mensajes_mes_limite_snapshot'] !== null) {
            $payload['messagesMonthlyLimit'] = (int) $order['mensajes_mes_limite_snapshot'];
        }

        try {
            $response = $this->api->activatePlan($tenantPublicId, $payload);
        } catch (LebytekApiException $e) {
            $this->orders->setApiActivationError($orderId, $e->getMessage());
            throw $e;
        }

        // Api 201 → string token; api semantic 200 → JSON null (decode as PHP null).
        // Fresh Idempotency-Key on every client call means retries land here, not on HTTP cache replay.
        $plainToken = trim((string) ($response['token'] ?? ''));

        $this->orders->markPaid($orderId, $authorizedBy);

        if ($plainToken !== '') {
            try {
                $this->sendMembershipEmail($order, $plainToken);
            } catch (\Throwable $mailError) {
                $this->orders->setApiActivationError($orderId, 'Correo: '.$mailError->getMessage());
            }
        }

        $updated = $this->orders->findById($orderId);

        return $updated ?? $order;
    }

    /** @param array<string, mixed> $order */
    private function sendMembershipEmail(array $order, string $token): void
    {
        $apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
        $cicloLabel = ($order['ciclo'] ?? '') === 'annual' ? 'Anual' : 'Mensual';
        $planLabel = ucfirst((string) ($order['paquete_slug'] ?? ''));

        $html = ViewHelper::render('emails/membership_activated', [
            'nombre' => (string) ($order['nombre'] ?? ''),
            'planNombre' => $planLabel,
            'ciclo' => $cicloLabel,
            'cuota' => number_format((float) ($order['precio_snapshot'] ?? 0), 2, '.', ','),
            'apiBaseUrl' => $apiBaseUrl,
            'token' => $token,
        ], '');

        $this->mailer->enviar(new MensajeCorreo(
            (string) ($order['email'] ?? ''),
            (string) ($order['nombre'] ?? ''),
            'Tu membresía Lebytek está activa',
            $html,
        ));
    }
}
