<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

use Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutSession;

/** Implemented by gateways that can create subscription Checkout sessions. */
interface SupportsSubscriptions
{
    /**
     * @param array{price_id:string,customer_email:string,success_url:string,cancel_url:string,external_ref:string,metadata?:array<string,string>} $input
     */
    public function createSubscriptionCheckout(array $input): CheckoutSession;
}
