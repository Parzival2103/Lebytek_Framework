<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;

/** Contrato genérico de checkout subscription y Billing Portal (issue #21). */
interface SupportsSubscriptions
{
    /**
     * @param array{
     *   price_id: string,
     *   customer_email: string,
     *   success_url: string,
     *   cancel_url: string,
     *   external_ref: string,
     *   metadata?: array<string, string>
     * } $params
     */
    public function createSubscriptionCheckout(array $params): CheckoutSession;

    /**
     * @param array{customer_id: string, return_url: string} $params
     */
    public function createBillingPortalSession(array $params): CheckoutSession;
}
