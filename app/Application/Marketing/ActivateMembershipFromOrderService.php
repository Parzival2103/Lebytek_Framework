<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Kernel\EnvLoader;

final class ActivateMembershipFromOrderService
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly LebytekApiClient $api,
        private readonly MarketingMailRenderer $mailRenderer,
        private readonly LeadRepositoryInterface $leads,
    ) {}

    /** Deterministic UUIDv5-shaped key from order public_id (activate-plan once per order). */
    public static function stableActivateIdempotencyKey(string $orderPublicId): string
    {
        $hex = hash('sha1', 'activate-plan|'.$orderPublicId);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * Admin transfer path: activate FIRST, then markPaid.
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function fromManualAuthorize(array $order, int $actorId): array
    {
        return $this->run($order, $actorId, markPaidFirst: false, idempotencyKey: null);
    }

    /**
     * Stripe path: markPaid FIRST (money captured), then activate with stable key.
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function fromConfirmedPayment(array $order, int $actorId, string $idempotencyKey): array
    {
        return $this->run($order, $actorId, markPaidFirst: true, idempotencyKey: $idempotencyKey);
    }

    /**
     * Orden ya pagada (Stripe u ops): reintentar activate-plan sin cambiar status.
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function fromPaidRetry(array $order, int $actorId): array
    {
        if ((string) ($order['status'] ?? '') !== 'paid') {
            throw new \InvalidArgumentException('Solo órdenes pagadas pueden activarse.');
        }

        $orderId = (int) ($order['id'] ?? 0);
        $tenantPublicId = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenantPublicId === '') {
            throw new \InvalidArgumentException('Asocia el tenant demo en la orden antes de activar.');
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
            'tokenName' => 'membresia-'.$slug,
        ];
        if ($slug === 'empresa' && isset($order['mensajes_mes_limite_snapshot']) && $order['mensajes_mes_limite_snapshot'] !== null) {
            $payload['messagesMonthlyLimit'] = (int) $order['mensajes_mes_limite_snapshot'];
        }

        $idempotencyKey = self::stableActivateIdempotencyKey((string) ($order['public_id'] ?? ''));

        try {
            $response = $this->api->activatePlan($tenantPublicId, $payload, $idempotencyKey);
        } catch (LebytekApiException $e) {
            $this->orders->setApiActivationError($orderId, $e->getMessage());
            throw $e;
        }

        $this->orders->clearApiActivationError($orderId);

        $this->markLeadConvertedIfPresent($order, $slug);

        $plainToken = trim((string) ($response['token'] ?? ''));
        if ($plainToken !== '') {
            try {
                $this->sendMembershipEmail($order, $plainToken);
            } catch (\Throwable $mailError) {
                $this->orders->setApiActivationError($orderId, 'Correo: '.$mailError->getMessage());
            }
        }

        return $this->orders->findById($orderId) ?? $order;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function run(array $order, int $actorId, bool $markPaidFirst, ?string $idempotencyKey): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        $tenantPublicId = trim((string) ($order['api_tenant_public_id'] ?? ''));

        if ($markPaidFirst) {
            $this->orders->markPaid($orderId, $actorId);
        }

        if ($tenantPublicId === '' && ! $markPaidFirst) {
            throw new \InvalidArgumentException('Asocia el tenant demo en la orden antes de autorizar.');
        }
        if ($tenantPublicId === '') {
            return $this->orders->findById($orderId) ?? $order;
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
            'tokenName' => 'membresia-'.$slug,
        ];
        if ($slug === 'empresa' && isset($order['mensajes_mes_limite_snapshot']) && $order['mensajes_mes_limite_snapshot'] !== null) {
            $payload['messagesMonthlyLimit'] = (int) $order['mensajes_mes_limite_snapshot'];
        }

        try {
            $response = $this->api->activatePlan($tenantPublicId, $payload, $idempotencyKey);
        } catch (LebytekApiException $e) {
            $this->orders->setApiActivationError($orderId, $e->getMessage());
            throw $e;
        }

        $plainToken = trim((string) ($response['token'] ?? ''));
        if (! $markPaidFirst) {
            $this->orders->markPaid($orderId, $actorId);
        }

        $this->markLeadConvertedIfPresent($order, $slug);

        if ($plainToken !== '') {
            try {
                $this->sendMembershipEmail($order, $plainToken);
            } catch (\Throwable $mailError) {
                $this->orders->setApiActivationError($orderId, 'Correo: '.$mailError->getMessage());
            }
        }

        return $this->orders->findById($orderId) ?? $order;
    }

    /** @param array<string, mixed> $order */
    private function markLeadConvertedIfPresent(array $order, string $planSlug): void
    {
        $leadId = (int) ($order['lead_id'] ?? 0);
        if ($leadId > 0) {
            $this->leads->markConverted($leadId, $planSlug);
        }
    }

    /** @param array<string, mixed> $order */
    private function sendMembershipEmail(array $order, string $token): void
    {
        $apiBaseUrl = rtrim((string) EnvLoader::get('LEBYTEK_API_URL', 'https://api.lebytek.com/api/v1'), '/');
        $cicloLabel = ($order['ciclo'] ?? '') === 'annual' ? 'Anual' : 'Mensual';
        $planLabel = ucfirst((string) ($order['paquete_slug'] ?? ''));

        $this->mailRenderer->send('membership_activated', (string) ($order['email'] ?? ''), (string) ($order['nombre'] ?? ''), [
            'nombre' => (string) ($order['nombre'] ?? ''),
            'plan' => $planLabel,
            'ciclo' => $cicloLabel,
            'cuota' => number_format((float) ($order['precio_snapshot'] ?? 0), 2, '.', ','),
            'api_base_url' => $apiBaseUrl,
            'token' => $token,
        ]);
    }
}
