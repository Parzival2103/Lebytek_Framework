<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Payments;

enum PaymentEventType: string
{
    case CheckoutCompleted = 'checkout.completed';
    case PaymentFailed = 'payment.failed';
    case InvoicePaid = 'invoice.paid';
    case InvoicePaymentFailed = 'invoice.payment_failed';
    case Ignored = 'ignored';
}
