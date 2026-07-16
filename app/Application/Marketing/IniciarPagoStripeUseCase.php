<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Kernel\EnvLoader;

final class IniciarPagoStripeUseCase
{
    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function ejecutar(int $orderId): string
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException('Orden no encontrada.');
        }
        if (($order['status'] ?? '') !== 'pending_payment') {
            throw new \InvalidArgumentException('La orden no está pendiente de pago con tarjeta.');
        }
        if (($order['metodo_pago'] ?? '') !== 'stripe') {
            throw new \InvalidArgumentException('La orden no usa Stripe.');
        }

        $publicId = (string) ($order['public_id'] ?? '');
        $baseUrl = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $currency = (string) EnvLoader::get('PAYMENTS_CURRENCY', 'mxn');
        $session = $this->gateways->get('stripe')->createCheckout(new CheckoutRequest(
            money: Money::fromMajor((float) ($order['precio_snapshot'] ?? 0), $currency),
            description: 'Membresía '.($order['paquete_slug'] ?? '').' — Lebytek',
            customerEmail: (string) ($order['email'] ?? ''),
            successUrl: $baseUrl.'/comprar/orden/'.$publicId.'/pago/exito',
            cancelUrl: $baseUrl.'/comprar/orden/'.$publicId.'/pago/cancelado',
            externalRef: $publicId,
            metadata: ['order_public_id' => $publicId],
            mode: 'payment',
        ));

        $this->orders->savePaymentRef($orderId, 'stripe', $session->providerSessionId());

        return $session->redirectUrl();
    }
}
