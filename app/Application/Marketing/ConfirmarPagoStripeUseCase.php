<?php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiException;
use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Payments\PaymentEventType;
use Lebytek\Framework\Domain\Payments\ValueObjects\Money;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class ConfirmarPagoStripeUseCase
{
    private const PROVIDER = 'stripe';

    public function __construct(
        private readonly MembershipOrderRepositoryInterface $orders,
        private readonly PaymentEventLogRepositoryInterface $eventLog,
        private readonly ActivateMembershipFromOrderService $activator,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly StartMembershipGraceService $startGrace,
        private readonly RecoverMembershipPaymentService $recover,
    ) {}

    public function ejecutar(PaymentEvent $event): void
    {
        if ($event->type() === PaymentEventType::Ignored) {
            $this->eventLog->tryClaim(
                self::PROVIDER,
                $event->providerEventId(),
                $event->externalRef(),
                $event->type()->value,
                hash('sha256', $event->providerEventId()),
            );
            return;
        }

        $orderRef = $event->externalRef();
        $claimed = $this->eventLog->tryClaim(
            self::PROVIDER,
            $event->providerEventId(),
            $orderRef,
            $event->type()->value,
            hash('sha256', $event->providerEventId().'|'.$orderRef),
        );
        if (! $claimed) {
            return;
        }

        try {
            $this->processClaimed($event);
        } catch (\Throwable $e) {
            AppLogger::error('[ConfirmarPagoStripe] post-claim failure swallowed', [
                'event_id' => $event->providerEventId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processClaimed(PaymentEvent $event): void
    {
        if ($event->type() === PaymentEventType::InvoicePaymentFailed) {
            $this->handleInvoicePaymentFailed($event);
            return;
        }

        if ($event->type() === PaymentEventType::InvoicePaid) {
            $this->handleInvoicePaid($event);
            return;
        }

        if ($event->type() === PaymentEventType::PaymentFailed) {
            $this->handleCheckoutPaymentFailed($event);
            return;
        }

        if ($event->type() === PaymentEventType::CheckoutCompleted) {
            if ($event->subscriptionId() !== null) {
                return;
            }
            $this->handleCheckoutCompleted($event);
        }
    }

    private function handleInvoicePaymentFailed(PaymentEvent $event): void
    {
        $membresia = $this->resolveMembresia($event);
        if ($membresia === null) {
            AppLogger::warning('[ConfirmarPagoStripe] invoice.payment_failed without membresía', [
                'subscription' => $event->subscriptionId(),
            ]);
            return;
        }

        $this->startGrace->handle($membresia, $event->providerEventId());
    }

    private function handleInvoicePaid(PaymentEvent $event): void
    {
        $membresia = $this->resolveMembresia($event);
        $order = $this->resolveOrder($event);

        if ($membresia !== null && in_array((string) ($membresia['status'] ?? ''), ['past_due', 'cancelled'], true)) {
            $this->recover->recoverAfterSuccessfulPayment($membresia, $event);
            return;
        }

        if ($order !== null && ($order['status'] ?? '') === 'pending_payment') {
            $this->handleCheckoutCompleted($event, $order);
            return;
        }

        if ($membresia !== null) {
            $this->memberships->bindStripeSubscription(
                (int) $membresia['id'],
                $event->customerId(),
                $event->subscriptionId(),
            );
            $this->memberships->markActive((int) $membresia['id']);
        }
    }

    private function handleCheckoutPaymentFailed(PaymentEvent $event): void
    {
        $order = $this->resolveOrder($event);
        if ($order === null) {
            return;
        }
        AppLogger::warning('[ConfirmarPagoStripe] payment failed; order left pending_payment', [
            'order_id' => $order['id'] ?? null,
        ]);
    }

    /** @param array<string, mixed>|null $order */
    private function handleCheckoutCompleted(PaymentEvent $event, ?array $order = null): void
    {
        $order ??= $this->resolveOrder($event);
        if ($order === null) {
            AppLogger::warning('[ConfirmarPagoStripe] order missing', [
                'ref' => $event->externalRef(),
            ]);
            return;
        }

        $status = (string) ($order['status'] ?? '');
        if ($status === 'paid') {
            return;
        }
        if ($status !== 'pending_payment') {
            AppLogger::warning('[ConfirmarPagoStripe] unexpected status', ['status' => $status]);
            return;
        }

        if ($event->money()->amountMinor() > 0) {
            $currency = (string) EnvLoader::get('PAYMENTS_CURRENCY', 'mxn');
            $expected = Money::fromMajor((float) ($order['precio_snapshot'] ?? 0), $currency);
            if (! $event->money()->equals($expected)) {
                AppLogger::error('[ConfirmarPagoStripe] amount/currency mismatch', [
                    'order_id' => $order['id'] ?? null,
                    'expected_minor' => $expected->amountMinor(),
                    'got_minor' => $event->money()->amountMinor(),
                ]);
                $this->orders->setApiActivationError(
                    (int) $order['id'],
                    'Pago Stripe con monto/moneda distinto al snapshot; revisión manual.'
                );
                return;
            }
        }

        $tenant = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenant === '') {
            $this->orders->markPaid((int) $order['id'], MembershipOrderActors::SYSTEM_WEBHOOK);
            $this->orders->setApiActivationError((int) $order['id'], 'Tenant no asociado; activación manual pendiente.');
            return;
        }

        $key = ActivateMembershipFromOrderService::stableActivateIdempotencyKey((string) $order['public_id']);
        try {
            $this->activator->fromConfirmedPayment($order, MembershipOrderActors::SYSTEM_WEBHOOK, $key);
        } catch (LebytekApiException $e) {
            AppLogger::error('[ConfirmarPagoStripe] activation failed after paid', [
                'order_id' => $order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->orders->setApiActivationError(
                (int) ($order['id'] ?? 0),
                'Activación falló tras el pago: '.$e->getMessage(),
            );
            AppLogger::error('[ConfirmarPagoStripe] activation failed after paid (non-api error)', [
                'order_id' => $order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $this->upsertMembresiaFromOrder($order, $event);
    }

    /** @param array<string, mixed> $order */
    private function upsertMembresiaFromOrder(array $order, PaymentEvent $event): void
    {
        $tenantId = trim((string) ($order['api_tenant_public_id'] ?? ''));
        if ($tenantId === '') {
            return;
        }

        $this->memberships->upsertFromActivation([
            'lead_id' => $order['lead_id'] ?? null,
            'api_tenant_public_id' => $tenantId,
            'plan_slug' => (string) ($order['paquete_slug'] ?? ''),
            'ciclo' => (string) ($order['ciclo'] ?? 'monthly'),
            'status' => 'active',
            'stripe_customer_id' => $event->customerId(),
            'stripe_subscription_id' => $event->subscriptionId(),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function resolveOrder(PaymentEvent $event): ?array
    {
        $ref = $event->externalRef();
        if ($ref === '') {
            return null;
        }

        return $this->orders->findByPublicId($ref);
    }

    /** @return array<string, mixed>|null */
    private function resolveMembresia(PaymentEvent $event): ?array
    {
        $subId = $event->subscriptionId();
        if ($subId !== null && $subId !== '') {
            $bySub = $this->memberships->findByStripeSubscriptionId($subId);
            if ($bySub !== null) {
                return $bySub;
            }
        }

        $order = $this->resolveOrder($event);
        if ($order !== null) {
            $tenantId = trim((string) ($order['api_tenant_public_id'] ?? ''));
            if ($tenantId !== '') {
                return $this->memberships->findByTenantPublicId($tenantId);
            }
        }

        return null;
    }
}
