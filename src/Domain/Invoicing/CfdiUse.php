<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

/** Subset SAT CFDI use codes used in CFDI I scaffold. */
enum CfdiUse: string
{
    case G01 = 'G01';
    case G03 = 'G03';
    case P01 = 'P01';
}
