<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\MembershipRepositoryInterface;
use App\Infrastructure\Integrations\LebytekApi\LebytekApiClient;
use App\Domain\Marketing\Contracts\ChurnMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Domain\Payments\SupportsSubscriptions;
use Lebytek\Framework\Domain\Payments\ValueObjects\PaymentEvent;
use Lebytek\Framework\Kernel\EnvLoader;

final class RecoverMembershipPaymentService
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly LeadRepositoryInterface $leads,
        private readonly ChurnMetricsRepositoryInterface $churnMetrics,
        private readonly LebytekApiClient $api,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /** @param array<string, mixed> $membresia */
    public function recoverAfterSuccessfulPayment(array $membresia, ?PaymentEvent $event = null): void
    {
        $id = (int) ($membresia['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        if ($event !== null) {
            $this->memberships->bindStripeSubscription($id, $event->customerId(), $event->subscriptionId());
        }

        $status = (string) ($membresia['status'] ?? 'active');
        $leadId = isset($membresia['lead_id']) ? (int) $membresia['lead_id'] : 0;
        $tenantId = (string) ($membresia['api_tenant_public_id'] ?? '');

        if ($status === 'cancelled' && $tenantId !== '') {
            try {
                $this->api->reactivateCommercial($tenantId, ['tokenName' => 'membresia-reactivated']);
            } catch (\Throwable) {
                // Webhook must stay 200; ops can retry reactivation manually.
            }
        }

        if ($status === 'past_due') {
            $this->memberships->clearGrace($id);
        }

        $this->memberships->markActive($id);

        if ($leadId > 0 && $status === 'cancelled') {
            $this->leads->clearCancelled($leadId);
        }

        $this->churnMetrics->resolveOpenRiskSignal(
            $leadId > 0 ? $leadId : null,
            $tenantId !== '' ? $tenantId : null,
            'payment_failed',
        );
    }

    public function findByRetryToken(string $rawToken): ?array
    {
        return $this->memberships->findByRetryTokenHash(hash('sha256', $rawToken));
    }

    public function findByReactivationToken(string $rawToken): ?array
    {
        return $this->memberships->findByReactivationTokenHash(hash('sha256', $rawToken));
    }

    /** @param array<string, mixed> $membresia */
    public function checkoutUrlForMembresia(array $membresia, string $successPath, string $cancelPath): string
    {
        $gateway = $this->gateways->get('stripe');
        if (! $gateway instanceof SupportsSubscriptions) {
            throw new \RuntimeException('Stripe subscription checkout is not available.');
        }

        $tenantId = (string) ($membresia['api_tenant_public_id'] ?? '');
        $planSlug = (string) ($membresia['plan_slug'] ?? '');
        $ciclo = (string) ($membresia['ciclo'] ?? 'monthly');
        $priceId = StripePriceResolver::resolve($planSlug, $ciclo);

        $leadId = isset($membresia['lead_id']) ? (int) $membresia['lead_id'] : 0;
        $lead = $leadId > 0 ? $this->leads->findById($leadId) : null;
        $email = (string) ($lead['email'] ?? '');

        $baseUrl = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
        $externalRef = $tenantId !== '' ? 'membresia-'.$tenantId : 'membresia-'.$membresia['id'];

        $session = $gateway->createSubscriptionCheckout([
            'price_id' => $priceId,
            'customer_email' => $email,
            'success_url' => $baseUrl.$successPath,
            'cancel_url' => $baseUrl.$cancelPath,
            'external_ref' => $externalRef,
            'metadata' => [
                'order_public_id' => $externalRef,
                'membresia_id' => (string) ($membresia['id'] ?? ''),
            ],
        ]);

        return $session->redirectUrl();
    }
}
