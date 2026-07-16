<?php
declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface;
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
    ) {}

    public function ejecutar(PaymentEvent $event): void
    {
        if ($event->type() === PaymentEventType::Ignored) {
            // Still claim so Stripe retries of noise are cheap no-ops.
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
            return; // already processed
        }

        // From here: NEVER throw to the webhook controller.
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
        $order = $this->orders->findByPublicId($event->externalRef());
        if ($order === null) {
            AppLogger::warning('[ConfirmarPagoStripe] order missing', [
                'ref' => $event->externalRef(),
            ]);
            return;
        }

        if ($event->type() === PaymentEventType::PaymentFailed) {
            AppLogger::warning('[ConfirmarPagoStripe] payment failed; order left pending_payment', [
                'order_id' => $order['id'] ?? null,
            ]);
            return;
        }

        if ($event->type() !== PaymentEventType::CheckoutCompleted) {
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
            // markPaid already done inside fromConfirmedPayment; keep 200 to Stripe.
            // ActivateMembershipFromOrderService already called setApiActivationError
            // for this exception type; just log here for observability.
            AppLogger::error('[ConfirmarPagoStripe] activation failed after paid', [
                'order_id' => $order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            // Non-HTTP failure (e.g. JSON encode, unexpected runtime error) after
            // markPaid; ActivateMembershipFromOrderService only records
            // setApiActivationError for LebytekApiException, so surface visibility
            // here too — the order must not stay "paid" with no error trail.
            $this->orders->setApiActivationError(
                (int) ($order['id'] ?? 0),
                'Activación falló tras el pago: '.$e->getMessage(),
            );
            AppLogger::error('[ConfirmarPagoStripe] activation failed after paid (non-api error)', [
                'order_id' => $order['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
