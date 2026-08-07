<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

/** Subset SAT payment form codes used in CFDI I scaffold. */
enum PaymentForm: string
{
    case Efectivo = '01';
    case Transferencia = '03';
    case TarjetaCredito = '04';
    case PorDefinir = '99';
}
