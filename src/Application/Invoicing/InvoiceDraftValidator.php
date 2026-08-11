<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceDraftInvalid;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;

final class InvoiceDraftValidator
{
    public function validate(InvoiceDraft $draft): void
    {
        $errors = [];
        $customer = $draft->customer();

        if (! preg_match('/^[A-Z&\x{D1}]{3,4}\d{6}[A-Z0-9]{3}$/u', strtoupper(trim($customer->taxId())))) {
            $errors[] = 'customer.taxId';
        }

        if (trim($customer->legalName()) === '') {
            $errors[] = 'customer.legalName';
        }

        if (! preg_match('/^\d{5}$/', trim($customer->address()->zip()))) {
            $errors[] = 'customer.address.zip';
        }

        if (! preg_match('/^\d{3}$/', trim($customer->taxSystem()))) {
            $errors[] = 'customer.taxSystem';
        }

        if (strtoupper(trim($draft->currency())) !== 'MXN') {
            $errors[] = 'currency';
        }

        $items = $draft->items();
        if ($items === []) {
            $errors[] = 'items';
        }

        foreach ($items as $index => $item) {
            if (! $item instanceof InvoiceItem) {
                $errors[] = "items.{$index}";
                continue;
            }

            if ($item->quantity() <= 0) {
                $errors[] = "items.{$index}.quantity";
            }

            if (! preg_match('/^\d{8}$/', trim($item->productKey()))) {
                $errors[] = "items.{$index}.productKey";
            }

            $unitKey = $item->unitKey();
            if ($unitKey === null || trim($unitKey) === '') {
                $errors[] = "items.{$index}.unitKey";
            }

            if (! $item->taxExempt() && $item->taxes() === []) {
                $errors[] = "items.{$index}.taxes";
            }
        }

        if ($errors !== []) {
            throw new InvoiceDraftInvalid('Invoice draft invalid: '.implode(', ', $errors));
        }
    }
}
