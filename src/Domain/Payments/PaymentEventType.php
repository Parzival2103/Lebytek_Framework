<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

enum PaymentEventType: string
{
    case CheckoutCompleted = 'checkout.completed';
    case PaymentFailed = 'payment.failed';
    case Ignored = 'ignored';
}
