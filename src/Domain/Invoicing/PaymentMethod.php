<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

/** CFDI payment method: PUE (single exhibition) or PPD (partial payments). */
enum PaymentMethod: string
{
    case Pue = 'PUE';
    case Ppd = 'PPD';
}
